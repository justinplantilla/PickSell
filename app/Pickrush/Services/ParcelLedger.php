<?php

namespace App\Pickrush\Services;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use App\Pickrush\Support\Actor;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The only way a parcel changes status. Writes the append-only event, refreshes the cached
 * status, current hub and next hub on the parcel, and announces the change once committed.
 * An idempotency key turns a retried scan (double tap, offline replay) into a no-op.
 */
class ParcelLedger
{
    private const IN_TRANSIT = [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation];

    private const LEFT_NETWORK = [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost];

    public function __construct(private HubRouter $router) {}

    /**
     * @param  array{to_hub?: ?Hub, rider_id?: ?int, note?: ?string, meta?: array, occurred_at?: ?CarbonInterface, idempotency_key?: ?string, actor?: ?Actor}  $options
     */
    public function record(Parcel $parcel, ParcelStatus $status, ?Hub $hub = null, array $options = []): ParcelEvent
    {
        $key = $options['idempotency_key'] ?? null;
        if ($key && $existing = ParcelEvent::where('idempotency_key', $key)->first()) {
            return $existing;
        }

        $actor = $options['actor'] ?? Actor::current();

        [$locked, $event] = DB::transaction(function () use ($parcel, $status, $hub, $options, $key, $actor) {
            $locked = Parcel::withoutGlobalScopes()->whereKey($parcel->id)->lockForUpdate()->firstOrFail();
            $from = $locked->current_status;
            if ($from !== null && ! $from->canMoveTo($status)) {
                throw new InvalidParcelTransition("Parcel {$locked->tracking_no} cannot go from \"{$from->label()}\" to \"{$status->label()}\".");
            }

            $toHub = $options['to_hub'] ?? null;
            $event = ParcelEvent::create([
                'parcel_id' => $locked->id,
                'status' => $status,
                'hub_id' => $hub?->id,
                'to_hub_id' => $toHub?->id,
                'rider_id' => $options['rider_id'] ?? null,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'note' => $options['note'] ?? null,
                'meta' => $options['meta'] ?? null,
                'idempotency_key' => $key,
                'occurred_at' => $options['occurred_at'] ?? now(),
            ]);

            $locked->forceFill($this->cachedState($locked, $status, $hub, $toHub))->save();

            return [$locked, $event];
        });

        $parcel->setRawAttributes($locked->getAttributes(), true);
        DB::afterCommit(fn () => ParcelStatusChanged::dispatch($locked, $event));

        return $event;
    }

    private function cachedState(Parcel $parcel, ParcelStatus $status, ?Hub $hub, ?Hub $toHub): array
    {
        $state = ['current_status' => $status];
        $returning = $parcel->is_return || $status === ParcelStatus::ReturningToSender;

        if ($status->isAtHub() && $hub) {
            $target = $returning ? $parcel->originHub : $parcel->destHub;
            $state['current_hub_id'] = $hub->id;
            $state['next_hub_id'] = $target ? $this->router->nextHop($hub, $target)?->id : null;
        } elseif (in_array($status, self::IN_TRANSIT, true)) {
            $state['current_hub_id'] = null;
            $state['next_hub_id'] = $toHub?->id;
        } elseif (in_array($status, self::LEFT_NETWORK, true)) {
            $state['current_hub_id'] = null;
            $state['next_hub_id'] = null;
        } elseif ($hub) {
            $state['current_hub_id'] = $hub->id;
        }

        if ($returning) {
            $state['is_return'] = true;
        }
        if ($status === ParcelStatus::DeliveryFailed) {
            $state['attempt_count'] = $parcel->attempt_count + 1;
        }
        if ($status === ParcelStatus::Delivered) {
            $state['delivered_at'] = now();
        }

        return $state;
    }
}
