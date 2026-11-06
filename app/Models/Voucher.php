<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A checkout voucher: a store's (seller_id set, the seller pays for it) or PickSell's own
 * (seller_id null, PickSell pays). See App\Services\Vouchers\VoucherPricing for how it applies.
 */
class Voucher extends Model
{
    public const FIXED = 'fixed';
    public const PERCENT = 'percent';
    public const FREE_SHIPPING = 'free_shipping';

    public const STORE_TYPES = [self::FIXED => '₱ off', self::PERCENT => '% off'];
    public const PLATFORM_TYPES = [self::FIXED => '₱ off', self::PERCENT => '% off', self::FREE_SHIPPING => 'Free shipping'];

    protected $fillable = [
        'seller_id', 'code', 'title', 'type', 'amount', 'min_spend', 'max_discount',
        'usage_limit', 'per_buyer_limit', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'min_spend' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
    public function redemptions() { return $this->hasMany(VoucherRedemption::class); }

    public function isPlatform(): bool
    {
        return $this->seller_id === null;
    }

    /** Switched on and inside its dates (it may still be used up or above the buyer's spend). */
    public function scopeLive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** "₱50 off · min. spend ₱500", "10% off up to ₱100", "Free shipping". */
    public function getSummaryAttribute(): string
    {
        $what = match ($this->type) {
            self::FIXED => '₱'.number_format((float) $this->amount, 0).' off',
            self::PERCENT => rtrim(rtrim(number_format((float) $this->amount, 2), '0'), '.').'% off'.($this->max_discount ? ' up to ₱'.number_format((float) $this->max_discount, 0) : ''),
            default => 'Free shipping'.($this->max_discount ? ' up to ₱'.number_format((float) $this->max_discount, 0) : ''),
        };

        return $what.((float) $this->min_spend > 0 ? ' · min. spend ₱'.number_format((float) $this->min_spend, 0) : '');
    }
}
