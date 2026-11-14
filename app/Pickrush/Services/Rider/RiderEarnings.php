<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Models\Assignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The rider's earnings ledger (pr_rider_earnings). A pickup, a delivery or a return to the seller
 * each pays the rider pay saved on the parcel at checkout, set by its parcel class: the pickup
 * rider carries the same weight as the delivery rider (the minimum rider pay for parcels booked
 * before classes existed); a failed attempt pays nothing. Each parcel's pickup, delivery and return is credited once: the unique
 * (parcel, kind) key ignores repeats.
 */
class RiderEarnings
{
    /** What finishing this task pays, in pesos (2-decimal string). */
    public static function payFor(Assignment $task): string
    {
        $amount = $task->parcel?->rider_pay ?? config('pickrush.shipping.min_rider_pay');

        return number_format((float) $amount, 2, '.', '');
    }

    /** Credit a finished pickup, delivery or return. */
    public function credit(Assignment $task, ?CarbonInterface $at = null): void
    {
        DB::table('pr_rider_earnings')->insertOrIgnore([
            'rider_id' => $task->rider_id,
            'parcel_id' => $task->parcel_id,
            'assignment_id' => $task->id,
            'kind' => $task->type,
            'amount' => self::payFor($task),
            'earned_at' => $at ?? now(),
            'created_at' => now(),
        ]);
    }
}
