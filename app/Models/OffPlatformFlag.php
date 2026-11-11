<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One sign that a seller may be taking a sale off PickSell, for admin review. */
class OffPlatformFlag extends Model
{
    public const KINDS = ['chat' => 'Chat', 'product' => 'Listing', 'order' => 'Order', 'report' => 'Buyer report'];

    /** signal => [label, weight] */
    public const SIGNALS = [
        'phone' => ['Phone number shared', 3],
        'email' => ['Email address shared', 3],
        'link' => ['Outside link or social account', 2],
        'payment' => ['Outside payment (GCash, bank…)', 2],
        'account' => ['Account number shared', 3],
        'courier' => ['Outside courier mentioned', 3],
        'phrase' => ['Asked to deal outside PickSell', 1],
        'keyword' => ['Watched keyword', 1],
        'cancel_after_contact' => ['Order cancelled right after contact details were shared', 4],
        'never_picked_up' => ['Waybill printed but parcel never handed to the carrier', 3],
        'high_cancellation' => ['High cancellation rate', 2],
        'buyer_report' => ['Buyer says the seller asked to deal outside PickSell', 5],
    ];

    public const ACTIONS = [
        'warned' => 'Warned (strike)',
        'case_opened' => 'Compliance case opened',
        'suspended' => 'Seller suspended',
        'listing_restored' => 'Listing approved and restored',
    ];

    /** Actions that count as a strike against the seller. */
    public const STRIKE_ACTIONS = ['warned', 'case_opened', 'suspended'];

    /** Risk is measured over this many days. */
    public const RISK_DAYS = 30;

    protected $guarded = ['id'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /** Flags that count toward risk: not dismissed, within the risk window. */
    public function scopeCounting(Builder $query): Builder
    {
        return $query->where('status', '!=', 'dismissed')->where('created_at', '>=', now()->subDays(self::RISK_DAYS));
    }

    public function label(): string
    {
        return self::SIGNALS[$this->signal][0] ?? ucfirst(str_replace('_', ' ', $this->signal));
    }

    public static function weightOf(string $signal): int
    {
        return self::SIGNALS[$signal][1] ?? 1;
    }
}
