<?php

namespace Tests\Feature;

use App\Models\ProductReview;
use App\Notifications\SellerRepliedToReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerReviewReplyTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_sellers_reply_publicly_to_reviews(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $buyer = $this->buyer();
        $lamp = $this->product($seller);
        $order = $this->order($buyer, $lamp, 'completed', ['rating' => 3, 'seller_rating' => 4, 'delivery_rating' => 4, 'feedback' => 'Dim bulb']);
        $review = ProductReview::create(['product_id' => $lamp->id, 'buyer_id' => $buyer->id, 'order_id' => $order->id, 'rating' => 3, 'body' => 'Dim bulb']);

        $this->actingAs($seller)->get(route('seller.reviews'))->assertOk()->assertSee('Reply publicly');
        $this->actingAs($seller)->put(route('seller.reviews.reply', $review), ['seller_reply' => 'Sorry! Try the 9W bulb we include.'])->assertSessionHasNoErrors();

        $this->assertSame('Sorry! Try the 9W bulb we include.', $review->fresh()->seller_reply);
        Notification::assertSentTo($buyer, SellerRepliedToReview::class);
        $this->actingAs($this->buyer())->get(route('buyer.product', $lamp))->assertOk()->assertSee("Seller's reply", false)->assertSee('Try the 9W bulb');
        $this->getJson("/api/mobile/products/{$lamp->id}/reviews")->assertJsonPath('reviews.0.seller_reply', 'Sorry! Try the 9W bulb we include.');

        // Editing does not notify again; an empty reply takes it down.
        $this->actingAs($seller)->put(route('seller.reviews.reply', $review), ['seller_reply' => 'Updated']);
        Notification::assertSentToTimes($buyer, SellerRepliedToReview::class, 1);
        $this->actingAs($seller)->put(route('seller.reviews.reply', $review), ['seller_reply' => '']);
        $this->assertNull($review->fresh()->seller_reply);

        $this->actingAs($this->seller())->put(route('seller.reviews.reply', $review), ['seller_reply' => 'Not mine'])->assertNotFound();
    }
}
