<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Zone;

/**
 * Suggests the rider for a parcel from its drop-off barangay: the zone's default rider, then the
 * backup, then (when their vehicles are too small for the parcel) another station rider whose
 * vehicle fits. Nothing is assigned automatically; the dispatcher confirms or overrides.
 */
class ZoneSuggester
{
    public function __construct(private RiderAvailability $availability) {}

    /** @return array{zone: ?Zone, rider: ?Rider, note: string} */
    public function suggest(Parcel $parcel, Hub $station): array
    {
        $zone = $this->zoneFor($parcel, $station);
        if (! $zone) {
            return ['zone' => null, 'rider' => null, 'note' => 'No zone: this barangay is not in any zone yet.'];
        }

        if ($zone->max_parcels && $this->zoneLoad($zone) >= $zone->max_parcels) {
            return ['zone' => $zone, 'rider' => null, 'note' => "{$zone->name} is at its load cap ({$zone->max_parcels}). Move the excess to a neighbouring zone."];
        }

        $notes = [];
        $zoneRiders = array_filter([$zone->defaultRider, $zone->backupRider]);
        foreach ($zoneRiders as $rider) {
            $blocker = $this->availability->blocker($rider, $parcel);
            if (! $blocker) {
                return ['zone' => $zone, 'rider' => $rider, 'note' => $rider->is($zone->defaultRider) ? 'Zone default rider' : 'Backup rider (default unavailable)'];
            }
            $notes[] = $blocker;
        }

        // The zone's riders cannot fit the parcel: look for anyone at the station whose vehicle can.
        if (collect($zoneRiders)->contains(fn (Rider $rider) => ! $rider->canCarry($parcel->parcel_class))) {
            return ['zone' => $zone] + $this->biggerVehicle($parcel, $station, $zoneRiders);
        }

        return ['zone' => $zone, 'rider' => null, 'note' => $notes ? implode(' ', $notes) : "{$zone->name} has no rider set."];
    }

    /**
     * @param  Rider[]  $skip  the zone's riders, already tried
     * @return array{rider: ?Rider, note: string}
     */
    private function biggerVehicle(Parcel $parcel, Hub $station, array $skip): array
    {
        $class = $parcel->parcel_class;
        $fit = Rider::withoutGlobalScope('hub')->where('station_hub_id', $station->id)->active()->withExists('tokens')->orderBy('full_name')->get()
            ->reject(fn (Rider $rider) => collect($skip)->contains(fn (Rider $zoneRider) => $zoneRider->is($rider)))
            ->filter(fn (Rider $rider) => $rider->canCarry($class));
        if ($fit->isEmpty()) {
            return ['rider' => null, 'note' => "No rider at {$station->name} has a vehicle for {$class} parcels (needs a ".Rider::vehiclesFor($class).').'];
        }
        $rider = $fit->first(fn (Rider $rider) => ! $this->availability->blocker($rider, $parcel));

        return $rider
            ? ['rider' => $rider, 'note' => "Station {$rider->vehicleName()} rider (zone riders can't carry {$class})"]
            : ['rider' => null, 'note' => "Every rider whose vehicle fits {$class} is unavailable right now."];
    }

    public function zoneFor(Parcel $parcel, Hub $station): ?Zone
    {
        $code = $parcel->dropOffBarangayCode();

        return $code ? Zone::withoutGlobalScope('hub')->with('defaultRider', 'backupRider')
            ->where('station_hub_id', $station->id)
            ->whereHas('barangayLinks', fn ($q) => $q->where('barangay_code', $code))
            ->first() : null;
    }

    private function zoneLoad(Zone $zone): int
    {
        $codes = $zone->barangayLinks()->pluck('barangay_code');

        return Assignment::whereIn('pr_assignments.status', Assignment::OPEN)->where('pr_assignments.type', '!=', 'pickup')
            ->join('pr_parcels', 'pr_parcels.id', '=', 'pr_assignments.parcel_id')
            ->where(fn ($q) => $q->whereIn('pr_parcels.delivery_barangay_code', $codes))
            ->count();
    }
}
