<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'variation_id', 'quantity'];

    public function cart()      { return $this->belongsTo(Cart::class); }
    public function product()   { return $this->belongsTo(Product::class); }
    public function variation() { return $this->belongsTo(ProductVariation::class, 'variation_id'); }

    /** One unit after the product's discount, at the chosen option's price. */
    public function getUnitPriceAttribute(): float
    {
        return $this->product->priceFor($this->variation);
    }

    public function getSubtotalAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }
}
