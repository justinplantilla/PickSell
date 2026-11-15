<?php

namespace App\Pickrush\Support;

use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Trip and fleet alerts on a hub's dashboard: its trips overdue on the road, and the papers of its
 * own trucks and drivers that are expired or due for renewal.
 */
final class FleetAlerts
{
    /** @return list<array{level: string, title: string, items: Collection}> */
    public static function forHub(Hub $hub): array
    {
        $trip = fn (Manifest $m) => route($hub->isStation() ? 'pickrush.station.trips.show' : 'pickrush.province.trips.show', $m);

        $overdue = Manifest::withoutGlobalScopes()->with('fromHub', 'toHub', 'driver')->overdue()
            ->where(fn ($q) => $q->where('from_hub_id', $hub->id)->orWhere('to_hub_id', $hub->id))->oldest('due_at')->get()
            ->map(fn (Manifest $m) => [
                'label' => "{$m->manifest_no} · {$m->fromHub?->name} → {$m->toHub?->name}",
                'meta' => "{$m->vehicle_plate} · {$m->driver_name}".($m->driver?->mobile_no ? " ({$m->driver->mobile_no})" : '')
                    .' · due '.$m->due_at->format('M j, g:i A').', '.$m->due_at->diffForHumans(null, true).' late',
                'url' => $trip($m),
            ]);

        $papers = Vehicle::withoutGlobalScopes()->where('hub_id', $hub->id)->where('is_active', true)->get()
            ->filter(fn (Vehicle $v) => Expiry::state($v->hasPapers() ? $v->registration_expiry : null) !== 'valid')
            ->map(fn (Vehicle $v) => ['label' => "{$v->plate_no} · OR/CR", 'expiry' => $v->hasPapers() ? $v->registration_expiry : null])
            ->concat(Driver::withoutGlobalScopes()->where('hub_id', $hub->id)->where('is_active', true)->get()
                ->filter(fn (Driver $d) => Expiry::state($d->license_expiry) !== 'valid')
                ->map(fn (Driver $d) => ['label' => "{$d->full_name} · license", 'expiry' => $d->license_expiry]))
            ->sortBy(fn ($p) => $p['expiry']?->timestamp ?? 0)->values()
            ->map(fn ($p) => ['label' => $p['label'], 'meta' => Expiry::badge($p['expiry'])[1].($p['expiry'] ? ' · '.$p['expiry']->format('M d, Y') : ''),
                'url' => route('pickrush.fleet.index')]);

        return array_values(array_filter([
            ['level' => 'bad', 'title' => 'Trips overdue', 'items' => $overdue],
            ['level' => 'warn', 'title' => 'Truck and driver papers to renew', 'items' => $papers],
        ], fn ($a) => $a['items']->isNotEmpty()));
    }
}
