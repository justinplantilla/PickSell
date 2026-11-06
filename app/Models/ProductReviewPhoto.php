<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A photo the buyer attached when rating an order; shown with that order's product review. */
class ProductReviewPhoto extends Model
{
    public const MAX_PER_REVIEW = 3;

    protected $fillable = ['order_id', 'path'];

    public function order() { return $this->belongsTo(Order::class); }
}
