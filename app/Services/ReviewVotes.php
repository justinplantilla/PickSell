<?php

namespace App\Services;

use App\Models\ProductReview;
use App\Models\ProductReviewVote;
use App\Models\User;

/**
 * "Helpful" votes on product reviews, one per buyer; nobody votes on their own review. Used by the
 * website's product page and the buyer app.
 */
class ReviewVotes
{
    /** @return array{helpful: bool, count: int} */
    public function set(User $buyer, ProductReview $review, bool $helpful): array
    {
        abort_if($review->buyer_id === $buyer->id, 422, 'You cannot vote on your own review.');
        $vote = ['product_review_id' => $review->id, 'user_id' => $buyer->id];
        $helpful ? ProductReviewVote::firstOrCreate($vote) : ProductReviewVote::where($vote)->delete();

        return ['helpful' => $helpful, 'count' => $review->votes()->count()];
    }

    /** The website's single Helpful button. @return array{helpful: bool, count: int} */
    public function toggle(User $buyer, ProductReview $review): array
    {
        abort_if($review->buyer_id === $buyer->id, 422, 'You cannot vote on your own review.');

        return $this->set($buyer, $review, ! ProductReviewVote::where(['product_review_id' => $review->id, 'user_id' => $buyer->id])->exists());
    }
}
