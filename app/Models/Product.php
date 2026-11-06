<?php

namespace App\Models;

use App\Services\WishlistAlerts;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const MAX_IMAGES = 6;
    public const LOW_STOCK_THRESHOLD = 5;

    protected $fillable = [
        'seller_id', 'name', 'description', 'category',
        'price', 'discount', 'voucher_code', 'voucher_discount',
        'stock', 'is_featured', 'status',
        'weight_kg',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'weight_kg' => 'decimal:2',
    ];

    // Product cards on nearly every page show the primary image, so keep images eager-loaded.
    protected $with = ['images'];

    protected static function booted(): void
    {
        // Price drops and restocks reach the buyers who wishlisted the product.
        static::updated(fn (Product $product) => WishlistAlerts::productUpdated($product));
    }

    public function seller()     { return $this->belongsTo(User::class, 'seller_id'); }
    public function orders()     { return $this->hasMany(Order::class); }
    public function variations() { return $this->hasMany(ProductVariation::class)->orderBy('id'); }
    public function cartItems()  { return $this->hasMany(CartItem::class); }
    public function wishlistItems() { return $this->hasMany(WishlistItem::class); }
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

    /** Products buyers can see and wishlist: active, and sold by an approved seller (even one on vacation). */
    public function scopeShowcased($query)
    {
        return $query->where('status', 'active')->whereHas('seller', fn ($seller) => $seller->where('status', 'approved'));
    }

    /** Products that may enter new orders: showcased, and the seller is not on vacation. */
    public function scopePurchasable($query)
    {
        return $query->where('status', 'active')
            ->whereHas('seller', fn ($seller) => $seller->where('status', 'approved')->takingOrders());
    }

    public function isShowcased(): bool
    {
        return $this->status === 'active' && $this->seller?->status === 'approved';
    }

    public function isPurchasable(): bool
    {
        return $this->status === 'active' && $this->seller?->status === 'approved' && ! $this->seller->isOnVacation();
    }

    /** Shipping is priced on weight; without it the item cannot be checked out. */
    public function hasShippingWeight(): bool
    {
        return $this->weight_kg !== null && (float) $this->weight_kg > 0;
    }

    public function sellerOnVacation(): bool
    {
        return (bool) $this->seller?->isOnVacation();
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->priceFor(null);
    }

    /** What one unit costs after the product's discount: the option's own price when it has one. */
    public function priceFor(?ProductVariation $variation): float
    {
        $base = $variation?->price !== null ? (float) $variation->price : (float) $this->price;

        return $base * (1 - $this->discount / 100);
    }

    /** The cheapest and dearest unit price across its options (equal when they share the product's price). */
    public function priceRange(): array
    {
        $prices = $this->variations->map(fn (ProductVariation $v) => $this->priceFor($v));
        if ($prices->isEmpty()) {
            return [$this->effective_price, $this->effective_price];
        }

        return [$prices->min(), $prices->max()];
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
