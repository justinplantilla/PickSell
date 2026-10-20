<?php

namespace App\Pickrush\Services\Hq;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\RiderDocument;

/**
 * What needs HQ's attention, most urgent first. Computed live; every alert links to the parcel,
 * hub or rider it is about.
 */
class Alerts
{
    /** @return list<array{priority: int, level: string, title: string, detail: string, items: \Illuminate\Support\Collection}> */
    public function all(): array
    {
        $stuckHours = (int) config('pickrush.policies.stuck_after_hours');
        $open = [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost];

        $alerts = [
            [1, 'bad', 'Cash shortages and overages', 'Rider remittances with an unresolved variance.',
                CodRemittance::withoutGlobalScopes()->with('rider.station')->where('status', 'disputed')->latest()->get()
                    ->map(fn ($r) => ['label' => "{$r->rider->full_name} · ₱".number_format((float) $r->variance, 2), 'meta' => $r->rider->station?->name, 'url' => null])],
            [1, 'bad', 'Station remittances that do not match', 'Submitted amounts different from the counted cash.',
                HubRemittance::withoutGlobalScopes()->with('station')->where('status', 'submitted')->where('variance', '!=', 0)->get()
                    ->map(fn ($r) => ['label' => "{$r->station->name} · ₱".number_format((float) $r->variance, 2), 'meta' => $r->reference_no, 'url' => route('pickrush.hq.finance')])],
            [2, 'bad', 'Missing parcels', 'Listed on a trip but never scanned in.',
                ParcelException::withoutGlobalScopes()->with('parcel', 'hub', 'againstHub')->where('type', 'missing')->where('status', 'open')->get()
                    ->map(fn ($e) => ['label' => $e->parcel?->tracking_no, 'meta' => "at {$e->hub->name}, against {$e->againstHub?->name}", 'url' => $e->parcel ? route('pickrush.parcels.show', $e->parcel) : null])],
            [2, 'warn', "Stuck parcels (no scan for {$stuckHours}h+)", 'Still in the network with no movement.',
                Parcel::withoutGlobalScopes()->with('currentHub', 'nextHub')->whereNotIn('current_status', $open)
                    ->where('updated_at', '<', now()->subHours($stuckHours))->oldest('updated_at')->limit(50)->get()
                    ->map(fn ($p) => ['label' => $p->tracking_no, 'meta' => $p->current_status->label().' · '.($p->currentHub?->name ?? $p->nextHub?->name ?? '—').' · '.$p->updated_at->diffForHumans(), 'url' => route('pickrush.parcels.show', $p)])],
            [3, 'warn', 'Addresses to fix', 'Parcels whose pickup or delivery address matched no station.',
                Parcel::withoutGlobalScopes()->where('needs_address_fix', true)->whereNotIn('current_status', $open)->latest()->limit(50)->get()
                    ->map(fn ($p) => ['label' => $p->tracking_no, 'meta' => $p->delivery_address, 'url' => route('pickrush.hq.address-fix')])],
            [3, 'warn', 'Riders with repeated failed deliveries', 'Three or more failed attempts in the last 7 days.',
                Assignment::with('rider.station')->where('status', 'failed')->where('completed_at', '>=', now()->subDays(7))
                    ->selectRaw('rider_id, count(*) as fails')->groupBy('rider_id')->having('fails', '>=', 3)->get()
                    ->map(fn ($a) => ['label' => "{$a->rider->full_name} · {$a->fails} failed", 'meta' => $a->rider->station?->name, 'url' => null])],
            [4, 'warn', 'Rider documents expiring in 30 days', 'Renew before they lapse.',
                RiderDocument::with('rider.station')->whereNotNull('expiry_date')->where('expiry_date', '<=', today()->addDays(30))->orderBy('expiry_date')->get()
                    ->map(fn ($d) => ['label' => "{$d->rider->full_name} · {$d->label()}", 'meta' => ($d->isExpired() ? 'expired ' : 'expires ').$d->expiry_date->format('M d, Y').' · '.$d->rider->station?->name, 'url' => null])],
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
