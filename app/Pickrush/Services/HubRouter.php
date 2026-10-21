<?php

namespace App\Pickrush\Services;

use App\Pickrush\Models\Hub;

/**
 * Network routing. A parcel moves station → its province hub → (destination province hub) →
 * destination station; a return runs the same chain towards the origin station.
 */
class HubRouter
{
    public function stationFor(?string $municipalityCode): ?Hub
    {
        return $municipalityCode ? Hub::stations()->where('psgc_code', $municipalityCode)->first() : null;
    }

    /** The hub a parcel at $current must go to next to reach $target, or null when it is already there. */
    public function nextHop(Hub $current, Hub $target): ?Hub
    {
        if ($current->is($target)) {
            return null;
        }
        if ($current->isStation()) {
            return $current->parent;
        }
        // At a province hub: hand over to the target's province hub first, then down to the station.
        $targetProvince = $target->provinceHub();

        return $current->is($targetProvince) ? $target : $targetProvince;
    }
}
