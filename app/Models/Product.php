<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const MAX_IMAGES = 6;
    public const LOW_STOCK_THRESHOLD = 5;

    protected $fillable = [
        'seller_id', 'name', 'description', 'category',
        'price', 'discount', 'voucher_code', 'voucher_discount',
        'stock', 'is_featured', 'status',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    // Product cards on nearly every page show the primary image, so keep images eager-loaded.
    protected $with = ['images'];

    public function seller()     { return $this->belongsTo(User::class, 'seller_id'); }
    public function orders()     { return $this->hasMany(Order::class); }
    public function variations() { return $this->hasMany(ProductVariation::class); }
    public function cartItems()  { return $this->hasMany(CartItem::class); }
    public function reviews()    { return $this->hasMany(ProductReview::class); }
    public function images()     { return $this->hasMany(ProductImage::class)->orderBy('display_order')->orderBy('id'); }

    public function moderationLogs() { return $this->hasMany(ProductModerationLog::class)->latest()->latest('id'); }

    /** The latest Admin archive/restore decision, if any. */
    public function latestStatusModeration()
    {
        return $this->hasOne(ProductModerationLog::class)->ofMany(['id' => 'max'], fn ($q) => $q->whereIn('action', ProductModerationLog::STATUS_ACTIONS));
    }

    /** Archived by an Admin and not restored since: only an Admin may restore it. */
    public function isUnderAdminHold(): bool
    {
        return $this->status === 'archived'
            && $this->latestStatusModeration?->action === ProductModerationLog::ARCHIVED;
    }

    /** Products that may enter new orders: active, and sold by an approved seller. */
    public function scopePurchasable($query)
    {
        return $query->where('status', 'active')->whereHas('seller', fn ($seller) => $seller->where('status', 'approved'));
    }

    public function isPurchasable(): bool
    {
        return $this->status === 'active' && $this->seller?->status === 'approved';
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->price * (1 - $this->discount / 100);
    }

    public function scopeLowStock($query)
    {
        return $query->where('status', 'active')->where('stock', '<=', self::LOW_STOCK_THRESHOLD);
    }

    /** One of: archived, out_of_stock, low_stock, in_stock. Archival takes precedence over stock level. */
    public function getStockStatusAttribute(): string
    {
        return match (true) {
            $this->status === 'archived'               => 'archived',
            $this->stock <= 0                          => 'out_of_stock',
            $this->stock <= self::LOW_STOCK_THRESHOLD  => 'low_stock',
            default                                    => 'in_stock',
        };
    }

    /** Storage path of the primary image (falls back to the first image), or null. */
    public function getPrimaryImageAttribute(): ?string
    {
        $image = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return $image?->image_url;
    }
}
