<?php

namespace App\Services\Logistics;

use App\Models\LogisticsIssue;
use App\Models\SellerStrike;
use App\Models\User;
use App\Notifications\SellerReturnUpdate;
use App\Services\AdminNotificationService;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Strikes against a seller for returns they did not take back (a refused-return issue resolved
 * as the seller's fault). Each lapses after LIFETIME_DAYS; HOLD_AT live strikes put the shop on
 * hold: its products stay visible but cannot be ordered (like vacation mode) until PickSell talks
 * with the seller and lifts the hold. Lapsed strikes never lift a hold by themselves.
 */
class SellerStrikes
{
    public const LIFETIME_DAYS = 90;

    public const HOLD_AT = 3;

    public function __construct(private AdminNotificationService $notifications, private AuditLogger $audit) {}

    public function active(User $seller): int
    {
        return SellerStrike::where('seller_id', $seller->id)->active()->count();
    }

    /** Strike the seller for this issue (once), and hold the shop at HOLD_AT live strikes. */
    public function strike(User $seller, LogisticsIssue $issue, User $admin, string $reason): SellerStrike
    {
        $strike = DB::transaction(function () use ($seller, $issue, $admin, $reason) {
            $strike = SellerStrike::firstOrCreate(['logistics_issue_id' => $issue->id], [
                'seller_id' => $seller->id,
                'reason' => $reason,
                'expires_at' => now()->addDays(self::LIFETIME_DAYS),
                'created_by' => $admin->id,
            ]);
            $issue->events()->create(['user_id' => $admin->id, 'action' => 'seller_struck',
                'note' => 'Strike '.$this->active($seller).' of '.self::HOLD_AT.' against '.($seller->business_name ?: $seller->full_name).'.']);

            return $strike;
        });

        $active = $this->active($seller);
        $order = $issue->order;
        if ($active >= self::HOLD_AT && ! $seller->isSellingHeld()) {
            $this->hold($seller, "{$active} strikes for returns not taken back in ".self::LIFETIME_DAYS.' days.');
        } elseif ($order) {
            $seller->notify(new SellerReturnUpdate($order, "You received a strike ({$active} of ".self::HOLD_AT.") for not taking back the return of order #{$order->order_number}. "
                .'Strikes lapse after '.self::LIFETIME_DAYS.' days; at '.self::HOLD_AT.' your shop stops taking orders until PickSell lifts the hold.'));
        }
        $this->audit->record('seller.strike.added', $seller, [], ['issue_id' => $issue->id, 'active' => $active]);

        return $strike;
    }

    public function hold(User $seller, string $reason): void
    {
        $seller->forceFill(['selling_held_at' => now(), 'selling_hold_reason' => $reason])->save();
        $seller->notify(new SellerReturnUpdate(null, "Your shop is on hold: {$reason} Buyers can still see your products but cannot order them. Contact PickSell to resolve it."));
        $this->notifications->notifyAdmins('seller.selling_held', 'Shop on hold',
            ($seller->business_name ?: $seller->full_name).": {$reason}", route('admin.compliance.seller', $seller, false), null, 'critical');
        $this->audit->record('seller.selling_held', $seller, [], ['reason' => $reason]);
    }

    /** PickSell talked with the seller and resolved it. Live strikes stay on record (and still count). */
    public function lift(User $seller, User $admin, string $note): void
    {
        if (! $seller->isSellingHeld()) {
            throw new DomainException('This shop is not on hold.');
        }
        $before = ['held_at' => $seller->selling_held_at?->toDateTimeString(), 'reason' => $seller->selling_hold_reason];
        $seller->forceFill(['selling_held_at' => null, 'selling_hold_reason' => null])->save();
        $this->audit->record('seller.selling_hold_lifted', $seller, $before, ['note' => $note, 'by' => $admin->id]);
    }
}
