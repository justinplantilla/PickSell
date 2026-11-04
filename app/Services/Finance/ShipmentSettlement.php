<?php

namespace App\Services\Finance;

use App\Models\OrderShipment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * What a shipment's delivery outcome does to the money. Delivered: the fee's split (rider,
 * logistics company, PickSell) is credited once, however often the event repeats. Failed:
 * nothing is credited; the reason and time are recorded. Returned or cancelled: the split is
 * void. Seller net and item commission follow the order to "completed" (FinancialLedgerService).
 */
class ShipmentSettlement
{
    public const PARTIES = ['rider' => 'rider_pay', 'logistics' => 'logistics_share', 'picksell' => 'platform_commission'];

    /** @return bool whether this call credited the split (false: already credited or void) */
    public function delivered(OrderShipment $shipment, ?CarbonInterface $at = null): bool
    {
        return DB::transaction(function () use ($shipment, $at) {
            $locked = OrderShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->earnings_posted_at || in_array($locked->status, OrderShipment::VOID, true)) {
                return false;
            }
            $now = $at ?? now();
            foreach (self::PARTIES as $party => $column) {
                // The unique (shipment, party) key is the idempotency key: a repeat is ignored.
                DB::table('shipment_earnings')->insertOrIgnore([
                    'order_shipment_id' => $locked->id, 'party' => $party, 'amount' => $locked->{$column}, 'posted_at' => $now,
                ]);
            }
            $locked->update(['status' => 'delivered', 'delivered_at' => $now, 'earnings_posted_at' => $now]);
            $shipment->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /** A failed attempt: no one is credited. The parcel may still be delivered on a later attempt. */
    public function failed(OrderShipment $shipment, ?string $reason, ?CarbonInterface $at = null): void
    {
        if ($shipment->earnings_posted_at || in_array($shipment->status, OrderShipment::VOID, true)) {
            return;
        }
        $shipment->update(['status' => 'failed', 'failed_at' => $at ?? now(), 'failure_reason' => $reason]);

        // EXTENSION POINT: what follows a failed delivery is not decided yet (redelivery,
        // return to seller, delivery fee refund or a return fee). Add that policy here; until
        // then the parcel follows Pickrush's own re-attempt / return rules and no money moves.
    }

    /** The parcel went back to the seller or was cancelled: the delivery split is never credited. */
    public function void(OrderShipment $shipment, string $status): void
    {
        if (! $shipment->earnings_posted_at) {
            $shipment->update(['status' => $status]);
        }
    }
}
