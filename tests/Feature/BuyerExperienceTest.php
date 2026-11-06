<?php

namespace Tests\Feature;

use App\Models\ProductReview;
use App\Rules\DeliverableEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class BuyerExperienceTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_for_couriers_on_the_landing_page_leads_to_the_pickrush_rider_section(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('href="https://'.config('pickrush.domain').'/#riders"', false);
    }

    public function test_mistyped_mailbox_providers_are_caught(): void
    {
        foreach (['gmail.net' => 'gmail.com', 'yahoo.net' => 'yahoo.com', 'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com',
            'hotmal.com' => 'hotmail.com', 'outlook.net' => 'outlook.com', 'gmail.con' => 'gmail.com'] as $typed => $meant) {
            $this->assertSame($meant, DeliverableEmail::suggestion($typed), $typed);
        }
        foreach (['gmail.com', 'yahoo.com.ph', 'mail.com', 'email.com', 'ymail.com', 'company.net', 'school.edu.ph'] as $fine) {
            $this->assertNull(DeliverableEmail::suggestion($fine), $fine);
        }
    }

    public function test_no_registration_code_is_sent_to_a_mistyped_address(): void
    {
        $this->postJson(route('register.email-code'), ['email' => 'juan@gmail.net'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Did you mean juan@gmail.com? Check the email address.');

        $this->postJson('/api/mobile/register/email-code', ['email' => 'juan@yahoo.net'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_the_cart_separates_items_by_store(): void
    {
        $lampShop = $this->seller(['business_name' => 'Lamp Shop']);
        $mugShop = $this->seller(['business_name' => 'Mug Shop']);
        $buyer = $this->buyer();
        $this->cartLine($buyer, $this->product($lampShop, ['name' => 'Desk Lamp']));
        $this->travel(1)->minutes();
        $this->cartLine($buyer, $this->product($mugShop, ['name' => 'Coffee Mug']));
        $this->travel(1)->minutes();
        $this->cartLine($buyer, $this->product($lampShop, ['name' => 'Floor Lamp']));

        $page = $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk();

        $this->assertSame(2, substr_count($page->getContent(), 'data-cart-select-store'));
        // Newest first, and each store's items stay under its own name.
        $page->assertSeeInOrder(['Lamp Shop', 'Floor Lamp', 'Desk Lamp', 'Mug Shop', 'Coffee Mug']);
    }

    public function test_buyer_rates_the_product_seller_and_delivery(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $lamp = $this->product($seller);
        $order = $this->order($buyer, $lamp, 'delivered');

        $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee('data-product="Desk Lamp"', false)
            ->assertSee('Seller service')->assertSee('Delivery');

        $this->actingAs($buyer)->post(route('buyer.feedback', $order), ['rating' => 5])
            ->assertSessionHasErrors(['seller_rating', 'delivery_rating']);
        $this->assertNull($order->fresh()->rating);

        $this->actingAs($buyer)->post(route('buyer.feedback', $order), ['rating' => 5, 'seller_rating' => 4, 'delivery_rating' => 3, 'feedback' => 'Bright lamp'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame([5, 4, 3], [$order->rating, $order->seller_rating, $order->delivery_rating]);
        // No Pickrush parcel here, so no rider to credit.
        $this->assertNull($order->delivery_rider_id);
        // The product review goes public once the seller confirms the delivery.
        $this->assertSame(0, ProductReview::count());
        $this->actingAs($seller)->patch(route('seller.orders.confirm-delivery', $order))->assertRedirect();
        $this->assertSame([5, 'Bright lamp'], [ProductReview::sole()->rating, ProductReview::sole()->body]);
        $this->assertSame(['average' => 4.0, 'count' => 1], $seller->sellerRating());

        $this->actingAs($buyer)->post(route('buyer.feedback', $order), ['rating' => 1, 'seller_rating' => 1, 'delivery_rating' => 1])->assertStatus(422);

        $this->actingAs($buyer)->get(route('buyer.seller', $seller))->assertOk()->assertSee('4.0');
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee('★ 4.0');
    }

    public function test_a_cancelled_order_keeps_its_single_experience_rating(): void
    {
        $buyer = $this->buyer();
        $order = $this->order($buyer, $this->product($this->seller()), 'cancelled');

        $this->actingAs($buyer)->post(route('buyer.feedback', $order), ['rating' => 2, 'feedback' => 'Too slow'])->assertSessionHasNoErrors();

        $this->assertSame([2, null, null], [$order->fresh()->rating, $order->fresh()->seller_rating, $order->fresh()->delivery_rating]);
        $this->assertSame(0, ProductReview::count());
    }

    public function test_the_app_can_send_all_three_ratings(): void
    {
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $order = $this->order($buyer, $this->product($seller), 'delivered');

        $this->postJson("/api/mobile/buyer/orders/{$order->id}/rate", ['rating' => 4, 'seller_rating' => 5, 'delivery_rating' => 2])
            ->assertOk()
            ->assertJsonPath('order.seller_rating', 5)
            ->assertJsonPath('order.delivery_rating', 2);
        $this->assertSame([4, 5, 2], [$order->fresh()->rating, $order->fresh()->seller_rating, $order->fresh()->delivery_rating]);
    }
}
