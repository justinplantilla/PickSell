<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Zone;

/**
 * Suggests the rider for a parcel from its drop-off barangay: the zone's default rider, then the
 * backup. Nothing is assigned automatically; the dispatcher confirms or overrides.
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
        foreach ([$zone->defaultRider, $zone->backupRider] as $rider) {
            if (! $rider) {
                continue;
            }
            $blocker = $this->availability->blocker($rider, $parcel);
            if (! $blocker) {
                return ['zone' => $zone, 'rider' => $rider, 'note' => $rider->is($zone->defaultRider) ? 'Zone default rider' : 'Backup rider (default unavailable)'];
            }
            $notes[] = $blocker;
        }

        return ['zone' => $zone, 'rider' => null, 'note' => $notes ? implode(' ', $notes) : "{$zone->name} has no rider set."];
    }

    public function zoneFor(Parcel $parcel, Hub $station): ?Zone
    {
        $code = $parcel->dropOffBarangayCode();

        return $code ? Zone::withoutGlobalScopes()->with('defaultRider', 'backupRider')
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
