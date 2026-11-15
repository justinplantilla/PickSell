<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One way a product is sold: a single option (Size: M) or a combination of up to three
 * (Color: Red, Size: M). options holds the [name, value] pairs in order; type and value keep the
 * same text joined with " / " for older readers (the buyer app shows them as one option group).
 */
class ProductVariation extends Model
{
    public const MAX_OPTION_NAMES = 3;
    public const MAX_VALUES_PER_OPTION = 10;
    public const MAX_COMBINATIONS = 50;

    protected $fillable = ['product_id', 'type', 'value', 'options', 'price', 'stock', 'image_id'];

    protected $casts = ['price' => 'decimal:2', 'options' => 'array'];

    public function product() { return $this->belongsTo(Product::class); }
    /** One of the product's gallery images, shown when a buyer picks this option. */
    public function image()   { return $this->belongsTo(ProductImage::class, 'image_id'); }

    /** @return list<array{0: string, 1: string}>  the [name, value] pairs, e.g. [["Color", "Red"], ["Size", "M"]] */
    public function optionPairs(): array
    {
        return $this->options ?: [[$this->type, $this->value]];
    }

    /** @return list<string> */
    public function optionValues(): array
    {
        return array_column($this->optionPairs(), 1);
    }

    /** How carts and orders name the choice, e.g. "Size: XL" or "Color: Red, Size: M". */
    public function getLabelAttribute(): string
    {
        return collect($this->optionPairs())->map(fn (array $pair) => ucfirst($pair[0]).': '.$pair[1])->join(', ');
    }
}
