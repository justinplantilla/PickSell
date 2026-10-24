<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Services\Rider\RiderBatches;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Services\Station\RiderAvailability;
use App\Pickrush\Services\Station\ZoneSuggester;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Dispatch queue: parcels waiting for a rider (deliveries, retries, returns to sellers, and seller
 * pickups) with the zone's suggested rider. The dispatcher confirms or overrides, one or many.
 * Pickups are grouped by seller (one stop each, as the rider app shows them) and can be filtered
 * by the seller's store name; the pickups given to one rider in one go become a batch the rider
 * accepts in the rider app.
 */
class DispatchController extends StationController
{
    public function index(Request $request, ZoneSuggester $suggester, RiderAvailability $availability)
    {
        $station = $this->station();
        $view = in_array($request->query('view'), ['pickups', 'no_zone', 'assigned'], true) ? $request->query('view') : 'deliveries';

        $queue = Parcel::where(fn ($q) => $q
            ->where(fn ($d) => $d->where('current_hub_id', $station->id)
                ->whereIn('current_status', [ParcelStatus::ArrivedAtStation, ParcelStatus::AtOriginHub, ParcelStatus::DeliveryFailed, ParcelStatus::ReturningToSender]))
            ->orWhere(fn ($p) => $p->where('origin_hub_id', $station->id)->where('current_status', ParcelStatus::WaitingPickup)))
            ->whereDoesntHave('openAssignment')
            ->whereDoesntHave('assignments', fn ($q) => DispatchService::stillWithRider($q))
            ->with('latestAssignment.rider')
            ->oldest('updated_at')->limit(300)->get()
            ->filter(fn (Parcel $parcel) => DispatchService::taskFor($parcel, $station) !== null)
            ->map(fn (Parcel $parcel) => (object) [
                'parcel' => $parcel,
                'task' => DispatchService::taskFor($parcel, $station),
                'suggestion' => $suggester->suggest($parcel, $station),
            ]);

        // Sellers with pickups waiting, for the filter and the quick picks.
        $sellers = $queue->where('task', 'pickup')->groupBy(fn ($row) => $row->parcel->shipper_name)
            ->map(fn ($rows, $name) => (object) ['name' => $name, 'count' => $rows->count()])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $seller = trim((string) $request->query('seller', ''));
        $queue = $queue->filter(fn ($row) => self::fromSeller($row->parcel->shipper_name, $seller))->values();

        $rows = match ($view) {
            'pickups' => $queue->where('task', 'pickup')->sortBy(fn ($row) => Str::lower($row->parcel->shipper_name))->values(),
            'no_zone' => $queue->filter(fn ($row) => $row->task !== 'pickup' && ! $row->suggestion['zone']),
            'assigned' => collect(),
            default => $queue->where('task', '!=', 'pickup'),
        };

        $riders = Rider::where('station_hub_id', $station->id)->where('status', 'active')->with('zone')->orderBy('full_name')->get()
            ->map(fn (Rider $rider) => (object) [
                'rider' => $rider,
                'open' => $availability->openCount($rider),
                'cod' => $availability->codAssigned($rider) + $availability->codInHand($rider),
                'blocker' => $availability->blocker($rider),
            ]);

        return view('pickrush.station.dispatch', [
            'view' => $view,
            'seller' => $seller,
            'sellers' => $sellers,
            'rows' => $rows->values(),
            // A stop is one seller at one address, the same grouping the rider app uses.
            'stops' => $view === 'pickups'
                ? $rows->groupBy(fn ($row) => $row->parcel->shipper_name.'|'.$row->parcel->pickup_address)->values()
                : collect(),
            'counts' => [
                'deliveries' => $queue->where('task', '!=', 'pickup')->count(),
                'pickups' => $queue->where('task', 'pickup')->count(),
                'no_zone' => $queue->filter(fn ($row) => $row->task !== 'pickup' && ! $row->suggestion['zone'])->count(),
            ],
            'assigned' => $view === 'assigned'
                ? Assignment::with('parcel', 'rider', 'batch')->whereIn('status', Assignment::OPEN)
                    ->whereIn('rider_id', $riders->pluck('rider.id'))->latest('assigned_at')->get()
                    ->filter(fn (Assignment $task) => self::fromSeller($task->parcel->shipper_name, $seller))->values()
                : collect(),
            'riders' => $riders,
        ]);
    }

    /**
     * Assign the ticked parcels: to the chosen rider, or each to its suggested rider. The pickups
     * that go to one rider become one pickup batch, and the deliveries and returns one delivery
     * batch; the rider is notified in the rider app.
     */
    public function assign(Request $request, DispatchService $dispatch, ZoneSuggester $suggester, RiderBatches $batches)
    {
        $data = $request->validate([
            'parcel_ids' => 'required|array|min:1|max:200',
            'parcel_ids.*' => 'integer',
            'rider_id' => 'nullable|integer',
        ]);
        $station = $this->station();
        $chosen = ($data['rider_id'] ?? null) ? Rider::where('station_hub_id', $station->id)->findOrFail($data['rider_id']) : null;

        $done = 0;
        $problems = [];
        $offers = [];
        foreach (Parcel::whereIn('id', $data['parcel_ids'])->get() as $parcel) {
            $rider = $chosen ?? $suggester->suggest($parcel, $station)['rider'];
            if (! $rider) {
                $problems[] = "{$parcel->tracking_no}: no suggested rider; pick one.";

                continue;
            }
            try {
                $assignment = $dispatch->assign($parcel, $rider, $this->staff());
                $kind = $assignment->type === 'pickup' ? 'pickup' : 'delivery';
                $offers["{$rider->id}:{$kind}"] ??= ['rider' => $rider, 'tasks' => collect()];
                $offers["{$rider->id}:{$kind}"]['tasks']->push($assignment->setRelation('parcel', $parcel));
                $done++;
            } catch (DomainException $e) {
                $problems[] = "{$parcel->tracking_no}: {$e->getMessage()}";
            }
        }
        foreach ($offers as $offer) {
            $batches->offer($offer['rider'], $offer['tasks'], $this->staff());
        }

        $response = back()->with('success', "{$done} parcel(s) assigned.");

        return $problems ? $response->withErrors(['operation' => implode(' ', array_slice($problems, 0, 10))]) : $response;
    }

    public function unassign(Request $request, Assignment $assignment, DispatchService $dispatch)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);

        return $this->attempt(fn () => $dispatch->unassign($assignment, $this->staff(), $data['reason']), 'Task taken back; the parcel is in the queue again.');
    }

    /** Store name filter: case-insensitive, matches any part of the name. */
    private static function fromSeller(?string $shipper, string $seller): bool
    {
        return $seller === '' || Str::contains((string) $shipper, $seller, ignoreCase: true);
    }
}
