<?php

namespace App\Pickrush\Bridge;

use App\Models\Order;
use App\Models\User;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Support\Actor;
use App\Services\Geo\AddressResolver;
use Illuminate\Support\Collection;

/**
 * The single seam between the Picksell marketplace and Pickrush, shaped like a carrier API:
 * the marketplace books a parcel for an order and reads its tracking; Pickrush pushes status
 * back through SyncOrderWithParcel. Nothing else in the marketplace touches pr_* tables.
 */
class PicksellBridge
{
    public const SOURCE = 'picksell';

    public function __construct(
        private ParcelRegistrar $registrar,
        private ParcelLedger $ledger,
        private AddressResolver $addresses,
    ) {}

    /** Book the parcel for an order that is ready for pickup. Idempotent: one parcel per order. */
    public function book(Order $order): Parcel
    {
        if ($existing = self::parcelFor($order)) {
            return $existing;
        }

        $seller = $order->seller;
        $buyer = $order->buyer;
        $pickup = $this->codes($seller);
        $delivery = $this->codes($buyer);

        return $this->registrar->register([
            'source' => self::SOURCE,
            'external_ref' => (string) $order->id,
            'shipper_name' => $seller?->business_name ?: $seller?->full_name ?: 'Picksell seller',
            'shipper_mobile' => $seller?->contact_no,
            'pickup_address' => self::address($seller),
            'pickup_barangay_code' => $pickup['barangay_code'],
            'pickup_municipality_code' => $pickup['municipality_code'],
            'consignee_name' => $buyer?->full_name ?? 'Picksell buyer',
            'consignee_mobile' => $buyer?->contact_no,
            'delivery_address' => self::address($buyer),
            'delivery_barangay_code' => $delivery['barangay_code'],
            'delivery_municipality_code' => $delivery['municipality_code'],
            'item_description' => "{$order->product_name} × {$order->quantity}",
            'cod_amount' => $order->amount,
        ], Actor::picksell());
    }

    /**
     * Called when the marketplace cancels an order. Only a parcel still waiting for pickup can be
     * cancelled; once Pickrush has it, the order must go through a return instead.
     */
    public function cancel(Order $order, string $reason): void
    {
        $parcel = self::parcelFor($order);
        if (! $parcel || $parcel->current_status === ParcelStatus::Cancelled) {
            return;
        }
        abort_unless($parcel->current_status === ParcelStatus::WaitingPickup, 409,
            "Pickrush already has parcel {$parcel->tracking_no} ({$parcel->current_status->label()}); it cannot be cancelled, only returned.");

        $this->ledger->record($parcel, ParcelStatus::Cancelled, null, ['note' => $reason, 'actor' => Actor::picksell()]);
    }

    public static function parcelFor(Order $order): ?Parcel
    {
        return Parcel::withoutGlobalScopes()->where('source', self::SOURCE)->where('external_ref', (string) $order->id)->first();
    }

    public static function hasParcel(Order $order): bool
    {
        return Parcel::withoutGlobalScopes()->where('source', self::SOURCE)->where('external_ref', (string) $order->id)->exists();
    }

    public static function orderFor(Parcel $parcel): ?Order
    {
        return $parcel->source === self::SOURCE ? Order::find((int) $parcel->external_ref) : null;
    }

    /**
     * Tracking for marketplace pages: one entry per hub scan, newest first.
     *
     * @return array{tracking_no: string, status: string, events: list<array{headline: string, at: \Carbon\CarbonInterface}>}|null
     */
    public static function tracking(Order $order): ?array
    {
        $parcel = self::parcelFor($order);
        if (! $parcel) {
            return null;
        }
        $parcel->load('events.hub', 'events.toHub');

        return [
            'tracking_no' => $parcel->tracking_no,
            'status' => $parcel->current_status->label(),
            'events' => $parcel->events->reverse()->values()
                ->map(fn ($event) => ['headline' => $event->headline(), 'at' => $event->occurred_at])->all(),
        ];
    }

    /** @return Collection<int, Parcel> parcels keyed by order id, for list pages */
    public static function parcelsFor(iterable $orders): Collection
    {
        $ids = collect($orders)->map(fn (Order $order) => (string) $order->id)->all();

        return Parcel::withoutGlobalScopes()->where('source', self::SOURCE)->whereIn('external_ref', $ids)
            ->get()->keyBy(fn (Parcel $parcel) => (int) $parcel->external_ref);
    }

    /** Codes saved on the account, or resolved now for accounts created before codes were stored. */
    private function codes(?User $user): array
    {
        if ($user?->municipality_code) {
            return ['municipality_code' => $user->municipality_code, 'barangay_code' => $user->barangay_code];
        }

        return $user ? $this->addresses->resolve($user->province, $user->municipality, $user->barangay)
            : ['municipality_code' => null, 'barangay_code' => null];
    }

    private static function address(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])
            ->filter(fn ($part) => filled($part))->join(', ') ?: null;
    }
}
