<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDocument;
use App\Pickrush\Models\Zone;
use App\Pickrush\Services\Station\CashHandling;
use App\Pickrush\Services\Station\RiderAvailability;
use App\Pickrush\Services\Station\RiderOnboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/** Rider management: applications from the rider app (riders upload their own documents), approval, limits, account status. */
class RiderController extends StationController
{
    public function __construct(private RiderOnboarding $onboarding) {}

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), Rider::STATUSES, true) ? $request->query('status') : null;
        // "pending" also covers applications verified under the old two-person check.
        $statuses = $status === 'pending' ? ['pending', 'under_review'] : [$status];

        return view('pickrush.station.riders', [
            'riders' => Rider::with('zone')->when($status, fn ($q) => $q->whereIn('status', $statuses))
                ->orderByRaw("case when status in ('pending', 'under_review') then 0 else 1 end")
                ->orderBy('applied_at')->orderBy('full_name')->get(),
            'status' => $status,
            'applications' => Rider::whereIn('status', ['pending', 'under_review'])->count(),
        ]);
    }

    public function show(Rider $rider, RiderAvailability $availability, CashHandling $cash)
    {
        $rider->load('zone', 'documents', 'creator', 'approver');

        return view('pickrush.station.rider', [
            'rider' => $rider,
            'required' => RiderOnboarding::requiredDocuments($rider),
            'documentLinks' => $rider->documents->mapWithKeys(fn (RiderDocument $doc) => [
                // Signs the path only: staff browse over https but the tunnel reaches Picksell over http.
                $doc->id => URL::temporarySignedRoute('pickrush.station.riders.document', now()->addMinutes(10), ['rider' => $rider, 'document' => $doc], absolute: false),
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

    /** Short-lived signed link; documents never sit on a public URL. */
    public function document(Rider $rider, RiderDocument $document)
    {
        abort_unless($document->rider_id === $rider->id, 404);
        AuditLog::record($document, 'viewed');

        return Storage::disk('local')->response($document->file_path);
    }

    public function approve(Rider $rider)
    {
        return $this->attempt(fn () => $this->onboarding->approve($rider, $this->staff()), "{$rider->full_name} is approved. They can now get parcels in the rider app.");
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
            'first_name' => 'required|string|max:60',
            'last_name' => 'required|string|max:60',
            'mobile_no' => ['required', 'regex:/^09\d{9}$/', Rule::unique('pr_riders', 'mobile_no')->ignore($rider?->id)],
            'birthdate' => 'required|date|before:-18 years',
            'address' => 'required|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_no' => ['nullable', 'regex:/^09\d{9}$/'],
            'vehicle_type' => ['required', Rule::in(array_keys(Rider::VEHICLE_TYPES))],
            'vehicle_model' => 'nullable|string|max:80',
            'plate_no' => 'required|string|max:20',
            'license_no' => 'nullable|string|max:30',
            'zone_id' => ['nullable', 'integer', Rule::exists('pr_zones', 'id')->where('station_hub_id', $this->station()->id)],
            'payout_method' => 'nullable|in:gcash,maya,bank',
            'payout_account' => 'nullable|string|max:60',
        ]);
    }
}
