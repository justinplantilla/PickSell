<?php

namespace Tests\Feature\BuyerApi;

use App\Models\ProductReview;
use App\Models\ProductReviewReport;
use App\Models\ProductReviewVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** "Helpful" and Report on product reviews, as on the website's product page (ReviewVotes, ReviewReports). */
class ReviewFeedbackTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function review(array $extra = []): ProductReview
    {
        $seller = $this->seller();
        $product = $this->product($seller);
        $author = $extra['buyer'] ?? $this->buyer();
        unset($extra['buyer']);
        $order = $this->order($author, $product, 'completed');

        return ProductReview::create($extra + ['product_id' => $product->id, 'buyer_id' => $author->id, 'order_id' => $order->id, 'rating' => 5, 'body' => 'Bright and sturdy.']);
    }

    public function test_buyers_mark_reviews_helpful_and_see_their_vote(): void
    {
        $review = $this->review();
        $reviews = "/api/mobile/products/{$review->product_id}/reviews";

        // Signed out: counts only.
        $this->getJson($reviews)->assertOk()
            ->assertJsonPath('reviews.0.helpful_count', 0)->assertJsonPath('reviews.0.is_helpful', false)->assertJsonPath('reviews.0.is_mine', false);

        $this->actingAsBuyer();
        $this->postJson("/api/mobile/buyer/reviews/{$review->id}/helpful")->assertOk()->assertExactJson(['helpful' => true, 'count' => 1]);
        $this->postJson("/api/mobile/buyer/reviews/{$review->id}/helpful")->assertOk()->assertExactJson(['helpful' => true, 'count' => 1], 'voting twice counts once');
        $this->getJson($reviews)->assertJsonPath('reviews.0.helpful_count', 1)->assertJsonPath('reviews.0.is_helpful', true);
        $this->getJson("/api/mobile/products/{$review->product_id}")->assertJsonPath('product.top_reviews.0.is_helpful', true);

        $this->deleteJson("/api/mobile/buyer/reviews/{$review->id}/helpful")->assertOk()->assertExactJson(['helpful' => false, 'count' => 0]);
        $this->assertSame(0, ProductReviewVote::count());
    }

    public function test_buyers_report_a_review_once_with_the_website_reasons(): void
    {
        $review = $this->review();
        $this->actingAsBuyer();

        $list = $this->getJson("/api/mobile/products/{$review->product_id}/reviews")->assertOk()->json();
        $this->assertSame(['fake', 'offensive', 'spam', 'personal_info', 'irrelevant'], array_column($list['report_reasons'], 'code'));
        $this->assertSame('Offensive or abusive language', $list['report_reasons'][1]['label']);

        $this->postJson("/api/mobile/buyer/reviews/{$review->id}/report", [])->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', 'Choose why you are reporting this review.');
        $this->postJson("/api/mobile/buyer/reviews/{$review->id}/report", ['reason' => 'spam', 'details' => 'Links to another shop'])->assertOk()
            ->assertExactJson(['reported' => true, 'message' => 'Thanks. Our team will look at this review.']);
        $this->postJson("/api/mobile/buyer/reviews/{$review->id}/report", ['reason' => 'fake'])->assertOk();

        $report = ProductReviewReport::sole();
        $this->assertSame(['fake', null], [$report->reason, $report->details], 'reporting again updates the one report');
        $this->getJson("/api/mobile/products/{$review->product_id}/reviews")->assertJsonPath('reviews.0.reported', true);
    }

    public function test_not_your_own_review_and_not_a_hidden_one(): void
    {
        $me = $this->buyer();
        $mine = $this->review(['buyer' => $me]);
        $hidden = $this->review(['hidden_at' => now()]);
        $this->actingAsBuyer($me);

        $this->getJson("/api/mobile/products/{$mine->product_id}/reviews")->assertJsonPath('reviews.0.is_mine', true);
        $this->postJson("/api/mobile/buyer/reviews/{$mine->id}/helpful")->assertStatus(422)->assertJsonPath('message', 'You cannot vote on your own review.');
        $this->postJson("/api/mobile/buyer/reviews/{$mine->id}/report", ['reason' => 'spam'])->assertStatus(422)->assertJsonPath('message', 'You cannot report your own review.');

        $this->postJson("/api/mobile/buyer/reviews/{$hidden->id}/helpful")->assertNotFound();
        $this->postJson("/api/mobile/buyer/reviews/{$hidden->id}/report", ['reason' => 'spam'])->assertNotFound();
        $this->assertSame([0, 0], [ProductReviewVote::count(), ProductReviewReport::count()]);
    }
}
