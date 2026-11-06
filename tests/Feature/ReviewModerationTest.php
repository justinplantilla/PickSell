<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ProductReview;
use App\Models\ProductReviewReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_reported_reviews_are_hidden_or_kept_by_admins(): void
    {
        $seller = $this->seller();
        $author = $this->buyer(['first_name' => 'Rude']);
        $reader = $this->buyer();
        $admin = $this->admin();
        $lamp = $this->product($seller);
        $order = $this->order($author, $lamp, 'completed', ['rating' => 1, 'feedback' => 'Awful seller, call me 0917']);
        $bad = ProductReview::create(['product_id' => $lamp->id, 'buyer_id' => $author->id, 'order_id' => $order->id, 'rating' => 1, 'body' => 'Awful seller, call me 0917']);
        ProductReview::create(['product_id' => $lamp->id, 'buyer_id' => $reader->id, 'rating' => 5, 'body' => 'Great']);

        // A buyer and the seller report it; the buyer cannot report their own review.
        $this->actingAs($reader)->post(route('buyer.reviews.report', $bad), ['reason' => 'personal_info', 'details' => 'Phone number'])->assertSessionHasNoErrors();
        $this->actingAs($seller)->post(route('seller.reviews.report', $bad), ['reason' => 'offensive'])->assertSessionHasNoErrors();
        $this->actingAs($author)->post(route('buyer.reviews.report', $bad), ['reason' => 'spam'])->assertStatus(422);
        $this->actingAs($reader)->post(route('buyer.reviews.report', $bad), [])->assertSessionHasErrors('reason');
        $this->assertSame(2, ProductReviewReport::count());

        $this->actingAs($admin)->get(route('admin.reviews'))->assertOk()
            ->assertSee('Awful seller')->assertSee('Shares personal information')->assertSee('Phone number');

        $this->actingAs($admin)->post(route('admin.reviews.hide', $bad), ['reason' => 'Shares a phone number'])->assertSessionHasNoErrors();
        $this->assertNotNull($bad->fresh()->hidden_at);
        $this->assertSame(0, ProductReviewReport::whereNull('resolved_at')->count());
        $this->assertSame(1, AuditLog::where('action', 'reviews.hidden')->count());

        // Hidden reviews leave the product page and its rating.
        $this->actingAs($reader)->get(route('buyer.product', $lamp))->assertOk()->assertDontSee('Awful seller');
        $this->assertSame(1, $lamp->reviews()->count());
        $this->getJson("/api/mobile/products/{$lamp->id}/reviews")->assertJsonCount(1, 'reviews');
        $this->actingAs($seller)->get(route('seller.reviews'))->assertSee('PickSell hid this review');

        $this->actingAs($admin)->post(route('admin.reviews.restore', $bad))->assertRedirect();
        $this->assertNull($bad->fresh()->hidden_at);
    }

    public function test_dismissing_keeps_the_review(): void
    {
        $lamp = $this->product($this->seller());
        $review = ProductReview::create(['product_id' => $lamp->id, 'buyer_id' => $this->buyer()->id, 'rating' => 2, 'body' => 'Meh']);
        $this->actingAs($this->buyer())->post(route('buyer.reviews.report', $review), ['reason' => 'fake']);

        $this->actingAs($this->admin())->post(route('admin.reviews.dismiss', $review))->assertRedirect();

        $this->assertNull($review->fresh()->hidden_at);
        $this->assertSame('kept', ProductReviewReport::sole()->resolution);
        $this->actingAs($this->seller())->post(route('seller.reviews.report', $review), ['reason' => 'fake'])->assertNotFound();
    }
}
