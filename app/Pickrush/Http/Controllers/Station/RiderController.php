<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDocument;
use App\Pickrush\Models\Zone;
use App\Pickrush\Services\Station\CashHandling;
use App\Pickrush\Services\Station\RiderAvailability;
use App\Pickrush\Services\Station\RiderOnboarding;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/** Rider management: staff-created accounts, documents, two-person verification, approval, limits. */
class RiderController extends StationController
{
    public function __construct(private RiderOnboarding $onboarding) {}

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), Rider::STATUSES, true) ? $request->query('status') : null;

        return view('pickrush.station.riders', [
            'riders' => Rider::with('zone')->when($status, fn ($q) => $q->where('status', $status))->orderBy('full_name')->get(),
            'status' => $status,
        ]);
    }

    public function create()
    {
        return view('pickrush.station.rider-form', ['zones' => Zone::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            $rider = $this->onboarding->create($data, $this->staff());
        } catch (DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pickrush.station.riders.show', $rider)->with('success', 'Rider account created as pending. Upload the documents next.');
    }

    public function show(Rider $rider, RiderAvailability $availability, CashHandling $cash)
    {
        $rider->load('zone', 'documents', 'creator', 'verifier');

        return view('pickrush.station.rider', [
            'rider' => $rider,
            'required' => RiderOnboarding::requiredDocuments($rider),
            'documentLinks' => $rider->documents->mapWithKeys(fn (RiderDocument $doc) => [
                $doc->id => URL::temporarySignedRoute('pickrush.station.riders.document', now()->addMinutes(10), ['rider' => $rider, 'document' => $doc]),
            ]),
            'open' => $rider->openAssignments()->with('parcel')->get(),
            'cashExpected' => $cash->expectedFrom($rider),
            'openCount' => $availability->openCount($rider),
            'zones' => Zone::orderBy('name')->get(),
            'history' => AuditLog::where('subject_type', 'Rider')->where('subject_id', $rider->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, Rider $rider)
    {
        $data = $this->validated($request, $rider);

        return $this->attempt(fn () => $rider->update($data), 'Rider details saved.');
    }

    public function uploadDocument(Request $request, Rider $rider)
    {
        $data = $request->validate([
            'doc_type' => ['required', Rule::in(array_keys(config('pickrush.rider_documents')))],
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'expiry_date' => 'nullable|date|after:today',
        ]);

        return $this->attempt(fn () => $this->onboarding->addDocument($rider, $data['doc_type'], $request->file('file'), $data['expiry_date'] ?? null, $this->staff()),
            fn (RiderDocument $doc) => "{$doc->label()} uploaded.");
    }

    /** Short-lived signed link; documents never sit on a public URL. */
    public function document(Rider $rider, RiderDocument $document)
    {
        abort_unless($document->rider_id === $rider->id, 404);
        AuditLog::record($document, 'viewed');

        return Storage::disk('local')->response($document->file_path);
    }

    public function verify(Rider $rider)
    {
        return $this->attempt(fn () => $this->onboarding->verify($rider, $this->staff()), 'Documents verified. The hub manager can now approve the account.');
    }

    public function approve(Rider $rider)
    {
        return $this->attempt(fn () => $this->onboarding->approve($rider, $this->staff()), "{$rider->full_name} is active. They can activate the rider app with their mobile number.");
    }

    public function changeStatus(Request $request, Rider $rider)
    {
        $data = $request->validate([
            'action' => 'required|in:reject,suspend,reactivate,deactivate',
            'reason' => 'required_unless:action,reactivate|nullable|string|max:500',
        ]);

        return $this->attempt(fn () => match ($data['action']) {
            'reject' => $this->onboarding->reject($rider, $this->staff(), $data['reason']),
            'suspend' => $this->onboarding->suspend($rider, $this->staff(), $data['reason']),
            'reactivate' => $this->onboarding->reactivate($rider, $this->staff()),
            'deactivate' => $this->onboarding->deactivate($rider, $this->staff(), $data['reason']),
        }, 'Rider status updated.');
    }

    public function limits(Request $request, Rider $rider)
    {
        $data = $request->validate([
            'cod_limit' => 'required|numeric|min:0|max:500000',
            'max_parcels' => 'required|integer|min:1|max:300',
            'on_probation' => 'nullable|boolean',
        ]);

        return $this->attempt(fn () => $this->onboarding->updateLimits($rider, $this->staff(), (float) $data['cod_limit'], (int) $data['max_parcels'], $request->boolean('on_probation')),
            'Limits updated.');
    }

    /**
     * Riders start and end shifts in the rider app. Until a rider has the app, station staff can
     * set it for them; every change is in the rider's history.
     */
    public function shift(Request $request, Rider $rider)
    {
        $data = $request->validate(['shift_status' => 'required|in:on_shift,off_duty']);
        if ($data['shift_status'] === 'on_shift' && ! $rider->isActive()) {
            return back()->withErrors(['operation' => 'Only an active rider can start a shift.']);
        }

        return $this->attempt(fn () => $rider->update($data), $data['shift_status'] === 'on_shift' ? "{$rider->full_name} is on shift." : "{$rider->full_name} is off duty.");
    }

    private function validated(Request $request, ?Rider $rider = null): array
    {
        return $request->validate([
            'full_name' => 'required|string|max:120',
            'mobile_no' => ['required', 'regex:/^09\d{9}$/', Rule::unique('pr_riders', 'mobile_no')->ignore($rider?->id)],
            'birthdate' => 'required|date|before:-18 years',
            'address' => 'required|string|max:255',
            'emergency_contact_name' => 'required|string|max:120',
            'emergency_contact_no' => ['required', 'regex:/^09\d{9}$/'],
            'vehicle_type' => 'required|in:motorcycle,bicycle,car,van,tricycle',
            'vehicle_model' => 'nullable|string|max:80',
            'plate_no' => 'required_unless:vehicle_type,bicycle|nullable|string|max:20',
            'zone_id' => ['nullable', 'integer', Rule::exists('pr_zones', 'id')->where('station_hub_id', $this->station()->id)],
            'payout_method' => 'nullable|in:gcash,maya,bank',
            'payout_account' => 'nullable|string|max:60',
        ]);
    }
}
