<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'store_discount' => 'decimal:2', 'product_discount' => 'decimal:2'];

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function variation() { return $this->belongsTo(ProductVariation::class); }
}
