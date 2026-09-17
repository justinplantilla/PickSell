<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'seller_id', 'name', 'description', 'category',
        'price', 'discount', 'voucher_code', 'voucher_discount',
        'stock', 'image', 'is_featured', 'status',
    ];

    public function seller()     { return $this->belongsTo(User::class, 'seller_id'); }
    public function orders()     { return $this->hasMany(Order::class); }
    public function variations() { return $this->hasMany(ProductVariation::class); }
    public function cartItems()  { return $this->hasMany(CartItem::class); }

    public function getEffectivePriceAttribute(): float
    {
        return $this->price * (1 - $this->discount / 100);
    }
}
