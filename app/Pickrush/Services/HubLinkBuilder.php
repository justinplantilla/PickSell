<?php

namespace App\Pickrush\Services;

use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubLink;

/**
 * Adds the starting province links (database/data/pickrush-province-links.php) for hubs that exist.
 * Re-running only adds missing pairs: hours, mode and on/off set by HQ are left alone, so a link
 * HQ turned off stays off.
 */
class HubLinkBuilder
{
    public function build(): int
    {
        $hubs = Hub::ofCompany(Company::defaultId())->provinces()->pluck('id', 'psgc_code');
        $created = 0;

        foreach (require database_path('data/pickrush-province-links.php') as [$one, $other, $mode, $hours]) {
            if (! isset($hubs[$one], $hubs[$other])) {
                continue;
            }
            [$a, $b] = HubLink::pair($hubs[$one], $hubs[$other]);
            $link = HubLink::firstOrCreate(['hub_a_id' => $a, 'hub_b_id' => $b], ['mode' => $mode, 'travel_hours' => $hours, 'is_active' => true]);
            $created += (int) $link->wasRecentlyCreated;
        }

        return $created;
    }
}
