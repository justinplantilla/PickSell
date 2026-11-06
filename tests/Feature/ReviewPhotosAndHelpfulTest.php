<?php

namespace Tests\Feature;

use App\Models\ProductReview;
use App\Models\ProductReviewPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ReviewPhotosAndHelpfulTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_photos_from_the_rating_show_with_the_product_review(): void
    {
        Storage::fake('public');
        $seller = $this->seller();
        $buyer = $this->buyer();
        $lamp = $this->product($seller);
        $order = $this->order($buyer, $lamp, 'delivered');

        $this->actingAs($buyer)->post(route('buyer.feedback', $order), [
            'rating' => 5, 'seller_rating' => 5, 'delivery_rating' => 5, 'feedback' => 'Bright',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, ProductReviewPhoto::where('order_id', $order->id)->count());
        Storage::disk('public')->assertExists(ProductReviewPhoto::first()->path);

        $this->actingAs($seller)->patch(route('seller.orders.confirm-delivery', $order))->assertRedirect();
        $this->actingAs($this->buyer())->get(route('buyer.product', $lamp))->assertOk()
            ->assertSee(Storage::url(ProductReviewPhoto::first()->path), false);
        $this->getJson("/api/mobile/products/{$lamp->id}/reviews")->assertOk()->assertJsonCount(2, 'reviews.0.photos');
    }

    public function test_too_many_or_wrong_photos_are_refused(): void
    {
        Storage::fake('public');
        $buyer = $this->buyer();
        $order = $this->order($buyer, $this->product($this->seller()), 'delivered');
        $stars = ['rating' => 5, 'seller_rating' => 5, 'delivery_rating' => 5];

        $this->actingAs($buyer)->post(route('buyer.feedback', $order), $stars + ['photos' => array_fill(0, 4, UploadedFile::fake()->image('x.jpg'))])
            ->assertSessionHasErrors('photos');
        $this->actingAs($buyer)->post(route('buyer.feedback', $order), $stars + ['photos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');
        $this->assertNull($order->fresh()->rating);
    }

    public function test_buyers_mark_reviews_helpful_once(): void
    {
        $seller = $this->seller();
        $author = $this->buyer();
        $reader = $this->buyer();
        $lamp = $this->product($seller);
        $review = ProductReview::create(['product_id' => $lamp->id, 'buyer_id' => $author->id, 'rating' => 4, 'body' => 'Good']);

        $this->actingAs($reader)->postJson(route('buyer.reviews.helpful', $review))->assertOk()->assertJson(['helpful' => true, 'count' => 1]);
        $this->actingAs($reader)->get(route('buyer.product', $lamp))->assertOk()->assertSee('Helpful (1)')->assertSee('aria-pressed="true"', false);
        $this->actingAs($reader)->postJson(route('buyer.reviews.helpful', $review))->assertOk()->assertJson(['helpful' => false, 'count' => 0]);

        $this->actingAs($author)->postJson(route('buyer.reviews.helpful', $review))->assertStatus(422);
    }
}
