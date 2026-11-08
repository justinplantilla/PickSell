<?php

namespace App\Http\Controllers\BuyerApi;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Services\ReviewVotes;
use App\Support\ReviewReports;
use Illuminate\Http\Request;

/**
 * Marking product reviews helpful and reporting them, as on the website's product page
 * (ReviewVotes, ReviewReports). Nobody votes on or reports their own review; a hidden review is gone.
 */
class ReviewController extends Controller
{
    public function __construct(private ReviewVotes $votes) {}

    /** POST /buyer/reviews/{review}/helpful -> {helpful: true, count} */
    public function helpful(Request $request, ProductReview $review)
    {
        return response()->json($this->votes->set($request->user(), $this->visible($review), true));
    }

    /** DELETE /buyer/reviews/{review}/helpful -> {helpful: false, count} */
    public function notHelpful(Request $request, ProductReview $review)
    {
        return response()->json($this->votes->set($request->user(), $this->visible($review), false));
    }

    /** POST /buyer/reviews/{review}/report {reason, details?} — one report per buyer; reporting again updates it. */
    public function report(Request $request, ProductReview $review)
    {
        abort_if($this->visible($review)->buyer_id === $request->user()->id, 422, 'You cannot report your own review.');
        ReviewReports::file($request, $review);

        return response()->json(['reported' => true, 'message' => 'Thanks. Our team will look at this review.']);
    }

    private function visible(ProductReview $review): ProductReview
    {
        abort_if($review->hidden_at !== null, 404, 'This review is no longer shown.');

        return $review;
    }
}
