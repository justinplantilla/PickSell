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
        return $this->discount_label.((float) $this->min_spend > 0 ? ' · min. spend ₱'.self::displayNumber($this->min_spend) : '');
    }

    private static function displayNumber(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }

    public function getDiscountLabelAttribute(): string
    {
        return match ($this->type) {
            self::FIXED => '₱'.self::displayNumber($this->amount).' off',
            self::PERCENT => self::displayNumber($this->amount).'% off'.($this->max_discount ? ' up to ₱'.self::displayNumber($this->max_discount) : ''),
            default => 'Free shipping'.($this->max_discount ? ' up to ₱'.self::displayNumber($this->max_discount) : ''),
        };
    }

    public function getBuyerConditionsAttribute(): string
    {
        $parts = [
            (float) $this->min_spend > 0 ? 'Minimum spend ₱'.self::displayNumber($this->min_spend) : 'No minimum spend',
            (int) $this->per_buyer_limit === 1 ? 'Once per buyer' : $this->per_buyer_limit.' uses per buyer',
        ];
        if ($this->usage_limit !== null) {
            $parts[] = $this->usage_limit.' total uses';
        }
        if ($this->starts_at) {
            $parts[] = 'Starts '.$this->starts_at->format('M j, Y g:i A');
        }
        if ($this->ends_at) {
            $parts[] = 'Ends '.$this->ends_at->format('M j, Y g:i A');
        }

        return implode(' · ', $parts);
    }
}
