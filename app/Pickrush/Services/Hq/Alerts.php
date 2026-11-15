<?php

namespace App\Pickrush\Services\Hq;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\RiderDocument;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\Station\RemittanceDeadline;
use App\Pickrush\Support\Expiry;
use App\Pickrush\Support\ParcelHubScope;

/**
 * What needs HQ's attention, most urgent first. Computed live; every alert links to the parcel,
 * hub or rider it is about.
 */
class Alerts
{
    /** Whether the station counted this rider's cash again after the rejected count. */
    private static function countedAgain(CodRemittance $rejected): bool
    {
        return CodRemittance::withoutGlobalScope('hub')->where('rider_id', $rejected->rider_id)->where('id', '>', $rejected->id)
            ->where('status', '!=', CodRemittance::REJECTED)->exists();
    }

    /** Stations holding counted cash past its deadline, oldest first. */
    private function lateStations(): \Illuminate\Support\Collection
    {
        $deadline = app(RemittanceDeadline::class);
        $holding = CodRemittance::withoutGlobalScope('hub')->whereIn('status', ['reconciled', 'disputed'])->whereNull('hub_remittance_id')->select('station_hub_id');
        $disputed = HubRemittance::withoutGlobalScope('hub')->where('status', 'disputed')->select('station_hub_id');

        return Hub::where(fn ($q) => $q->whereIn('id', $holding)->orWhereIn('id', $disputed))->get()
            ->map(fn (Hub $station) => ['station' => $station] + $deadline->for($station))
            ->whereIn('state', ['overdue', 'blocked'])->sortBy('due_at')->values()
            ->map(fn ($late) => [
                'label' => "{$late['station']->name} · ₱".number_format($late['amount'], 2),
                'meta' => 'Due '.$late['due_at']->format('M j, g:i A').($late['state'] === 'blocked' ? ' · deliveries stopped' : ' · deliveries stop '.$late['block_at']->format('M j, g:i A')),
                'url' => route('pickrush.hq.finance'),
            ]);
    }

    /** Active trucks' ORs and drivers' licenses expired or expiring, soonest first. */
    private function fleetPapers(): \Illuminate\Support\Collection
    {
        $until = today()->addDays(Expiry::warningDays());
        $trucks = Vehicle::withoutGlobalScope('hub')->with('hub')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('registration_expiry')->orWhere('registration_expiry', '<=', $until))->get()
            ->map(fn ($v) => ['label' => "{$v->plate_no} · OR/CR", 'expiry' => $v->registration_expiry, 'hub' => $v->hub?->name]);
        $drivers = Driver::withoutGlobalScope('hub')->with('hub')->where('is_active', true)->where('license_expiry', '<=', $until)->get()
            ->map(fn ($d) => ['label' => "{$d->full_name} · license", 'expiry' => $d->license_expiry, 'hub' => $d->hub?->name]);

        return $trucks->concat($drivers)->sortBy(fn ($p) => $p['expiry']?->timestamp ?? 0)->values()
            ->map(fn ($p) => ['label' => $p['label'], 'meta' => Expiry::badge($p['expiry'])[1].($p['expiry'] ? ' '.$p['expiry']->format('M d, Y') : '').' · '.$p['hub'], 'url' => null]);
    }

    /** @return list<array{priority: int, level: string, title: string, detail: string, items: \Illuminate\Support\Collection}> */
    public function all(): array
    {
        $stuckHours = (int) config('pickrush.policies.stuck_after_hours');
        $open = [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost, ParcelStatus::Abandoned];

        $alerts = [
            [1, 'bad', 'Cash shortages and overages', 'Rider remittances with an unresolved variance.',
                CodRemittance::withoutGlobalScope('hub')->with('rider.station')->where('status', 'disputed')->latest()->get()
                    ->map(fn ($r) => ['label' => "{$r->rider?->full_name} · ₱".number_format((float) $r->variance, 2), 'meta' => $r->rider?->station?->name, 'url' => null])],
            [1, 'bad', 'Cash counts riders rejected', 'In the last 7 days a rider said the station counted their cash wrong.',
                CodRemittance::withoutGlobalScope('hub')->with('rider.station', 'receiver')->where('status', CodRemittance::REJECTED)
                    ->where('rider_responded_at', '>=', now()->subDays(7))->latest('rider_responded_at')->get()
                    ->map(fn ($r) => ['label' => "{$r->rider?->full_name} · counted ₱".number_format((float) $r->received_amount, 2).' by '.($r->receiver?->full_name ?? 'staff'),
                        'meta' => $r->rider?->station?->name.' · "'.$r->rider_note.'"'.(self::countedAgain($r) ? ' · counted again' : ' · not counted again yet'), 'url' => null])],
            [1, 'bad', 'Stations late remitting cash', 'Counted cash past its deadline at HQ. After the grace hours the station cannot dispatch deliveries.',
                $this->lateStations()],
            [1, 'bad', 'Disputed with stations', 'Deposits HQ disputed, until the station tops them up or HQ confirms them after a contest.',
                HubRemittance::withoutGlobalScope('hub')->with('station')->where('status', 'disputed')->whereNull('settles_id')->oldest('confirmed_at')->get()
                    ->map(fn ($r) => ['label' => "{$r->station?->name} · ref {$r->reference_no}".($r->shortfall() > 0 ? ' · ₱'.number_format($r->shortfall(), 2).' short' : ''),
                        'meta' => 'Disputed '.$r->confirmed_at?->format('M j, g:i A').($r->pendingTopUp() ? ' · top-up waiting for HQ' : ' · waiting for the station'),
                        'url' => route('pickrush.hq.finance')])],
            [1, 'bad', 'Station remittances that do not match', 'Submitted amounts different from the counted cash.',
                HubRemittance::withoutGlobalScope('hub')->with('station')->where('status', 'submitted')->where('variance', '!=', 0)->get()
                    ->map(fn ($r) => ['label' => "{$r->station?->name} · ₱".number_format((float) $r->variance, 2), 'meta' => $r->reference_no, 'url' => route('pickrush.hq.finance')])],
            [2, 'bad', 'Missing parcels', 'Listed on a trip but never scanned in.',
                ParcelException::withoutGlobalScope('hub')->with('parcel', 'hub', 'againstHub')->where('type', 'missing')->where('status', 'open')->get()
                    ->map(fn ($e) => ['label' => $e->parcel?->tracking_no, 'meta' => "at {$e->hub?->name}, against {$e->againstHub?->name}", 'url' => $e->parcel ? route('pickrush.parcels.show', $e->parcel) : null])],
            [2, 'bad', 'Trucks overdue', 'Dispatched trips not marked arrived by their due time. Call the driver.',
                Manifest::withoutGlobalScope('hub')->with('fromHub', 'toHub', 'driver')->overdue()->oldest('due_at')->get()
                    ->map(fn ($m) => ['label' => "{$m->manifest_no} · {$m->fromHub?->name} → {$m->toHub?->name}",
                        'meta' => "{$m->vehicle_plate} · {$m->driver_name}".($m->driver?->mobile_no ? " ({$m->driver->mobile_no})" : '').' · '.$m->due_at->diffForHumans(null, true).' late', 'url' => null])],
            [2, 'warn', "Stuck parcels (no scan for {$stuckHours}h+)", 'Still in the network with no movement.',
                Parcel::withoutGlobalScope(ParcelHubScope::class)->with('currentHub', 'nextHub')->whereNotIn('current_status', $open)
                    ->where('updated_at', '<', now()->subHours($stuckHours))->oldest('updated_at')->limit(50)->get()
                    ->map(fn ($p) => ['label' => $p->tracking_no, 'meta' => $p->current_status->label().' · '.($p->currentHub?->name ?? $p->nextHub?->name ?? '—').' · '.$p->updated_at->diffForHumans(), 'url' => route('pickrush.parcels.show', $p)])],
            [3, 'warn', 'Addresses to fix', 'Parcels whose pickup or delivery address matched no station.',
                Parcel::withoutGlobalScope(ParcelHubScope::class)->where('needs_address_fix', true)->whereNotIn('current_status', $open)->latest()->limit(50)->get()
                    ->map(fn ($p) => ['label' => $p->tracking_no, 'meta' => $p->delivery_address, 'url' => route('pickrush.hq.address-fix')])],
            [3, 'warn', 'Riders with repeated failed deliveries', 'Three or more failed attempts in the last 7 days.',
                Assignment::with('rider.station')->where('status', 'failed')->where('completed_at', '>=', now()->subDays(7))
                    ->selectRaw('rider_id, count(*) as fails')->groupBy('rider_id')->having('fails', '>=', 3)->get()
                    ->map(fn ($a) => ['label' => "{$a->rider?->full_name} · {$a->fails} failed", 'meta' => $a->rider?->station?->name, 'url' => null])],
            [4, 'warn', 'Rider documents expiring in 30 days', 'Renew before they lapse.',
                RiderDocument::with('rider.station')->whereNull('replaced_at')->whereNotNull('expiry_date')->where('expiry_date', '<=', today()->addDays(30))->orderBy('expiry_date')->get()
                    ->map(fn ($d) => ['label' => "{$d->rider?->full_name} · {$d->label()}", 'meta' => ($d->isExpired() ? 'expired ' : 'expires ').$d->expiry_date->format('M d, Y').' · '.$d->rider?->station?->name, 'url' => null])],
            [4, 'warn', 'Truck and driver papers expiring in '.Expiry::warningDays().' days', 'Trucks with an expired OR and drivers with an expired license cannot take trips.',
                $this->fleetPapers()],
            [5, 'info', 'Hubs not ready', 'Active hubs still missing a manager, a zone or an approved rider.',
                collect([['label' => $this->notReadyCount().' hubs', 'meta' => 'See the hub directory', 'url' => route('pickrush.hubs.index', ['readiness' => 'no_manager'])]])],
        ];

        return collect($alerts)
            ->map(fn ($a) => ['priority' => $a[0], 'level' => $a[1], 'title' => $a[2], 'detail' => $a[3], 'items' => $a[4]])
            ->filter(fn ($a) => $a['items']->isNotEmpty() && ! ($a['title'] === 'Hubs not ready' && $this->notReadyCount() === 0))
            ->sortBy('priority')->values()->all();
    }

    private ?int $notReady = null;

    private function notReadyCount(): int
    {
        return $this->notReady ??= Hub::whereDoesntHave('staff', fn ($q) => $q->where('role', 'hub_manager')->where('is_active', true)->where('is_sample', false))->count();
    }
}
