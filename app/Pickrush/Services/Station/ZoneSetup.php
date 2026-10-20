<?php

namespace App\Pickrush\Services\Station;

use App\Models\Geo\Barangay;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Zone;
use App\Pickrush\Models\ZoneBarangay;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Support\Facades\DB;

/**
 * Zones group the station's barangays into one realistic day's route each. A barangay belongs to
 * exactly one zone; moving it only affects new assignments (in-flight parcels keep their rider).
 */
class ZoneSetup
{
    /** @param list<string> $barangayCodes */
    public function save(?Zone $zone, array $data, array $barangayCodes, Staff $manager): Zone
    {
        if (! $manager->hasRole(StaffRole::HubManager) || ! $manager->hub?->isStation()) {
            throw new OperationRefused('Only the station hub manager can set up zones.');
        }
        $station = $manager->hub;

        $valid = Barangay::where('municipality_code', $station->psgc_code)->whereIn('code', $barangayCodes)->pluck('code');
        if ($valid->count() !== count(array_unique($barangayCodes))) {
            throw new OperationRefused("Only barangays of {$station->municipality_name} can be in this station's zones.");
        }
        foreach (['default_rider_id', 'backup_rider_id'] as $field) {
            if (! empty($data[$field]) && ! Rider::whereKey($data[$field])->where('station_hub_id', $station->id)->exists()) {
                throw new OperationRefused('Zone riders must belong to this station.');
            }
        }
        if (! empty($data['default_rider_id']) && ($data['default_rider_id'] ?? null) == ($data['backup_rider_id'] ?? null)) {
            throw new OperationRefused('The backup rider must be someone other than the default rider.');
        }

        return DB::transaction(function () use ($zone, $data, $valid, $station) {
            $zone ??= new Zone(['station_hub_id' => $station->id]);
            $zone->fill([
                'name' => $data['name'],
                'default_rider_id' => ($data['default_rider_id'] ?? null) ?: null,
                'backup_rider_id' => ($data['backup_rider_id'] ?? null) ?: null,
                'max_parcels' => ($data['max_parcels'] ?? null) ?: null,
            ])->save();

            // Move the chosen barangays here, taking them out of any other zone of this station.
            ZoneBarangay::whereIn('barangay_code', $valid)->where('zone_id', '!=', $zone->id)->delete();
            ZoneBarangay::where('zone_id', $zone->id)->whereNotIn('barangay_code', $valid)->delete();
            $existing = ZoneBarangay::where('zone_id', $zone->id)->pluck('barangay_code');
            foreach ($valid->diff($existing) as $code) {
                ZoneBarangay::create(['zone_id' => $zone->id, 'barangay_code' => $code]);
            }

            return $zone;
        });
    }

    /** Barangays of the station with the zone each is in (null = not yet zoned). */
    public static function barangaysOf(Staff $staff)
    {
        $zoneByCode = ZoneBarangay::whereIn('zone_id', Zone::where('station_hub_id', $staff->hub_id)->select('id'))
            ->with('zone:id,name')->get()->keyBy('barangay_code');

        return Barangay::where('municipality_code', $staff->hub->psgc_code)->orderBy('name')->get()
            ->map(fn (Barangay $b) => (object) ['code' => $b->code, 'name' => $b->name, 'zone' => $zoneByCode[$b->code]->zone ?? null]);
    }
}
