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
        'weight_kg', 'preorder_days',
        'sale_discount', 'sale_starts_at', 'sale_ends_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'weight_kg' => 'decimal:2',
        'preorder_days' => 'integer',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
    ];

    // The seller's edit form needs the regular discount even while a sale is on.
    protected $appends = ['regular_discount'];

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
    /** Published reviews; ones an admin hid are left out everywhere, ratings included. */
    public function reviews()    { return $this->hasMany(ProductReview::class)->whereNull('hidden_at'); }
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

    /** A scheduled sale is running right now. */
    public function saleIsOn(): bool
    {
        $sale = $this->attributes['sale_discount'] ?? null;

        return $sale !== null && (float) $sale > 0 && $this->sale_starts_at && $this->sale_ends_at
            && $this->sale_starts_at->lte(now()) && $this->sale_ends_at->gte(now());
    }

    /** The discount buyers get now: the scheduled sale's while it runs, the regular one otherwise. */
    public function getDiscountAttribute($value)
    {
        return $this->saleIsOn() ? $this->attributes['sale_discount'] : $value;
    }

    public function getRegularDiscountAttribute()
    {
        return $this->attributes['discount'] ?? 0;
    }

    /** Products buyers get a discount on right now (a regular one or a running sale). */
    public function scopeOnSale($query)
    {
        return $query->where(fn ($q) => $q->where('discount', '>', 0)->orWhere(fn ($s) => $s->where('sale_discount', '>', 0)
            ->where('sale_starts_at', '<=', now())->where('sale_ends_at', '>=', now())));
    }

    /**
     * What buyers pay for one unit, as SQL with its bindings, so price filters and sorts follow
     * the discount and any running sale.
     *
     * @return array{0: string, 1: array}
     */
    public static function effectivePriceSql(): array
    {
        return ['price * (1 - (CASE WHEN sale_discount IS NOT NULL AND sale_starts_at <= ? AND sale_ends_at >= ? THEN sale_discount ELSE discount END) / 100.0)',
            [now()->toDateTimeString(), now()->toDateTimeString()]];
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
        // Low when the product as a whole, or any one of its options, is at or under the line.
        return $query->where('status', 'active')->where(fn ($q) => $q->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
            ->orWhereHas('variations', fn ($v) => $v->where('stock', '<=', self::LOW_STOCK_THRESHOLD)));
    }

    /** Options at or under the low-stock line (loads variations). */
    public function lowStockOptions()
    {
        return $this->variations->filter(fn (ProductVariation $v) => $v->stock <= self::LOW_STOCK_THRESHOLD)->values();
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
