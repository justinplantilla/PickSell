<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $fillable = ['product_id', 'buyer_id', 'order_id', 'rating', 'body', 'seller_reply', 'seller_replied_at', 'hidden_at', 'hidden_by', 'hidden_reason'];

    protected $casts = ['seller_replied_at' => 'datetime', 'hidden_at' => 'datetime'];

    public function product() { return $this->belongsTo(Product::class); }
    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function order() { return $this->belongsTo(Order::class); }

    /** Photos from the rating of the order this review came from. */
    public function photos() { return $this->hasMany(ProductReviewPhoto::class, 'order_id', 'order_id'); }

    /** Buyers who marked the review helpful. */
    public function votes() { return $this->hasMany(ProductReviewVote::class); }

    public function reports() { return $this->hasMany(ProductReviewReport::class); }

    public function openReports() { return $this->hasMany(ProductReviewReport::class)->whereNull('resolved_at'); }
}
