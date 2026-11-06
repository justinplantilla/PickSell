<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $fillable = ['product_id', 'type', 'value', 'price', 'stock'];

    protected $casts = ['price' => 'decimal:2'];

    public function product() { return $this->belongsTo(Product::class); }

    /** How carts and orders name the choice, e.g. "Size: XL". */
    public function getLabelAttribute(): string
    {
        return ucfirst($this->type).': '.$this->value;
    }
}
