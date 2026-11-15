<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDocument;
use App\Pickrush\Models\RiderDocumentUpdate;
use App\Pickrush\Models\Zone;
use App\Pickrush\Services\Rider\DocumentRenewal;
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
            'riders' => Rider::with('zone', 'documents')->withExists('tokens')
                ->withCount(['documentUpdates as updates_waiting' => fn ($q) => $q->where('status', 'pending')])->when($status, fn ($q) => $q->whereIn('status', $statuses))
                ->orderByRaw("case when status in ('pending', 'under_review') then 0 else 1 end")
                ->orderBy('applied_at')->orderBy('full_name')->get(),
            'status' => $status,
            'applications' => Rider::whereIn('status', ['pending', 'under_review'])->count(),
        ]);
    }

    public function show(Rider $rider, RiderAvailability $availability, CashHandling $cash)
    {
        $rider->load('zone', 'documents', 'documentHistory', 'creator', 'approver');
        // Signs the path only: staff browse over https but the tunnel reaches Picksell over http.
        $link = fn (RiderDocument $doc) => URL::temporarySignedRoute('pickrush.station.riders.document', now()->addMinutes(10), ['rider' => $rider, 'document' => $doc], absolute: false);
        $pending = $rider->documentUpdates()->where('status', 'pending')->oldest('id')->get();

        return view('pickrush.station.rider', [
            'rider' => $rider,
            'required' => RiderOnboarding::requiredDocuments($rider),
            'documentLinks' => $rider->documents->concat($rider->documentHistory)->mapWithKeys(fn (RiderDocument $doc) => [$doc->id => $link($doc)]),
            'pendingUpdates' => $pending,
            'updateLinks' => $pending->mapWithKeys(fn (RiderDocumentUpdate $update) => [$update->id => collect($update->files)->keys()
                ->map(fn (int $i) => URL::temporarySignedRoute('pickrush.station.riders.update-file', now()->addMinutes(10),
                    ['rider' => $rider, 'update' => $update->id, 'index' => $i], absolute: false))->all()]),
            'expired' => $rider->expiredDocument(),
            'open' => $rider->openAssignments()->with('parcel')->get(),
            'cashExpected' => $cash->expectedFrom($rider),
            'openCount' => $availability->openCount($rider),
            'rating' => PicksellBridge::riderRating($rider),
            'zones' => Zone::orderBy('name')->get(),
            'history' => AuditLog::where('subject_type', 'Rider')->where('subject_id', $rider->id)->latest('id')->limit(20)->get(),
        ]);
    }

    /**
     * The station only sets the rider's home zone. Their personal, vehicle and payout details were
     * checked against their documents and stay as the rider gave them.
     */
    public function update(Request $request, Rider $rider)
    {
        $data = $request->validate([
            'zone_id' => ['nullable', 'integer', Rule::exists('pr_zones', 'id')->where('station_hub_id', $this->station()->id)],
        ]);

        return $this->attempt(fn () => $rider->update($data), 'Home zone saved.');
    }

    /** Short-lived signed link; documents never sit on a public URL. */
    public function document(Rider $rider, RiderDocument $document)
    {
        abort_unless($document->rider_id === $rider->id, 404);
        AuditLog::record($document, 'viewed');

        return Storage::disk('local')->response($document->file_path);
    }

    /** A file of a renewal the rider sent and the station has not decided yet. */
    public function updateFile(Rider $rider, int $update, int $index)
    {
        $update = $rider->documentUpdates()->findOrFail($update);
        $path = $update->files[$index]['path'] ?? abort(404);
        AuditLog::record($update, 'viewed');

        return Storage::disk('local')->response($path);
    }

    /** Approve a renewed license or OR/CR (it replaces the old one), or reject it with a reason. */
    public function decideUpdate(Request $request, Rider $rider, int $update, DocumentRenewal $renewal)
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'reason' => 'required_if:decision,reject|nullable|string|max:500',
        ]);
        $update = $rider->documentUpdates()->findOrFail($update);

        return $data['decision'] === 'approve'
            ? $this->attempt(fn () => $renewal->approve($update, $this->staff()), "{$update->label()} approved. It replaces the old one.")
            : $this->attempt(fn () => $renewal->reject($update, $this->staff(), $data['reason']), "{$update->label()} rejected. The rider sees the reason in the app.");
    }

    /** The real expiry read off the OR/CR, for one the system gave an estimated date. */
    public function orcrExpiry(Request $request, Rider $rider, DocumentRenewal $renewal)
    {
        $data = $request->validate(['expiry_date' => 'required|date']);

        return $this->attempt(fn () => $renewal->setOrcrExpiry($rider, $this->staff(), $data['expiry_date']), 'OR/CR expiry saved.');
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
        if ($data['shift_status'] === 'on_shift' && ! $rider->isSignedIn()) {
            return back()->withErrors(['operation' => "{$rider->full_name} is logged out of the rider app. They must log in before starting a shift."]);
        }

        if ($data['shift_status'] === 'on_shift') {
            $data['last_seen_at'] = now();   // counts as a check-in; still goes offline if the app stays closed
        }

        return $this->attempt(fn () => $rider->update($data), $data['shift_status'] === 'on_shift' ? "{$rider->full_name} is on shift." : "{$rider->full_name} is off duty.");
    }
}
