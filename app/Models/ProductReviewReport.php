<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A buyer's or seller's report that a product review breaks the rules. */
class ProductReviewReport extends Model
{
    public const REASONS = [
        'fake' => 'Fake or not from a real purchase',
        'offensive' => 'Offensive or abusive language',
        'spam' => 'Spam or advertising',
        'personal_info' => 'Shares personal information',
        'irrelevant' => 'Not about this product',
    ];

    protected $fillable = ['product_review_id', 'user_id', 'reason', 'details', 'resolved_at', 'resolved_by', 'resolution'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function review() { return $this->belongsTo(ProductReview::class, 'product_review_id'); }
    public function reporter() { return $this->belongsTo(User::class, 'user_id'); }
}
