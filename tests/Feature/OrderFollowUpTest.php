<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrderFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private int $orders = 0;

    private function user(string $role): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'FollowUp',
            'email' => "{$role}-followup@example.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
            'business_name' => $role === 'seller' ? 'Follow Up Store' : null,
        ]);
    }

    private function order(User $buyer, User $seller, string $status, array $extra = []): Order
    {
        $this->orders++;

        return Order::create([
            'order_number' => "ORD-FOLLOWUP-{$this->orders}",
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Follow up product',
            'quantity' => 1,
            'amount' => 100,
            'commission' => 10,
            'status' => $status,
            ...$extra,
        ]);
    }

    public function test_seller_hand_over_and_cancel_ask_for_confirmation_once(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $ready = $this->order($buyer, $seller, 'preparing', ['waybill_number' => 'PR123']);

        $detail = $this->actingAs($seller)->get(route('seller.orders.show', $ready))->assertOk()->getContent();
        $this->assertStringNotContainsString('return confirm(', $detail);
        $this->assertStringContainsString('data-confirm="Is the parcel packed', $detail);

        // The hand-over modal is the confirmation, so the layout's generic prompt is skipped.
        $this->get(route('seller.orders'))->assertOk()->assertSee('id="handoverForm" data-no-confirm', false);
    }

    public function test_sorted_orders_file_under_at_sorting_center(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $this->order($buyer, $seller, 'sorted');
        $this->order($buyer, $seller, 'at_sorting_center');
        $this->order($buyer, $seller, 'picked_up');

        $this->actingAs($seller)->get(route('seller.orders', ['status' => 'at_sorting_center']))
            ->assertOk()
            ->assertSee('ORD-FOLLOWUP-1')
            ->assertSee('ORD-FOLLOWUP-2')
            ->assertDontSee('ORD-FOLLOWUP-3')
            ->assertDontSee('>Sorted<', false)
            ->assertDontSee('<option value="sorted"', false);
    }

    public function test_buyers_confirm_receipt_then_rate_and_the_review_is_published(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rated mug', 'category' => 'Home', 'price' => 100, 'stock' => 5, 'status' => 'active']);
        $order = $this->order($buyer, $seller, 'delivered', ['product_id' => $product->id]);

        $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()->assertSee('Order Received')->assertDontSee('Rate & Review', false);
        $this->actingAs($buyer)->patch(route('buyer.orders.received', $order))->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue($seller->notifications()->exists(), 'the seller hears the buyer received it');

        $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()->assertSee('Rate & Review', false);
        $this->post(route('buyer.feedback', $order), ['rating' => 4, 'seller_rating' => 5, 'delivery_rating' => 5, 'feedback' => 'Sturdy mug'])->assertRedirect();
        $this->assertDatabaseHas('product_reviews', ['order_id' => $order->id, 'product_id' => $product->id, 'rating' => 4, 'body' => 'Sturdy mug']);

        // Sellers no longer confirm deliveries.
        $other = $this->order($buyer, $seller, 'delivered', ['product_id' => $product->id]);
        $this->actingAs($seller)->patch(route('seller.orders.confirm-delivery', $other))->assertSessionHasErrors('order');
        $this->assertSame('delivered', $other->fresh()->status);
        $this->actingAs($seller)->get(route('seller.orders.show', $other))->assertOk()
            ->assertSee('once the buyer confirms they received it')->assertDontSee('Confirm Delivery');
    }

    public function test_orders_still_on_the_way_cannot_be_rated(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $order = $this->order($buyer, $seller, 'out_for_delivery');

        $this->actingAs($buyer)->post(route('buyer.feedback', $order), ['rating' => 5])->assertStatus(422);
        $this->assertNull($order->fresh()->rating);
    }

    public function test_an_order_card_holds_its_details_and_price_breakdown(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $order = $this->order($buyer, $seller, 'preparing', ['quantity' => 2, 'amount' => 250]);

        $this->actingAs($buyer)->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee('data-order-card', false)
            ->assertSee('aria-controls="order-details-'.$order->id.'"', false)
            ->assertSee('id="order-details-'.$order->id.'" hidden', false)
            ->assertSeeInOrder(['Item price', '₱125.00', 'Quantity', '× 2', 'Subtotal', '₱250.00', 'Shipping fee', 'Total', '₱250.00']);
    }

    public function test_buyer_chat_sends_without_a_confirmation_and_the_product_context_can_be_cleared(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Asked lamp', 'category' => 'Home', 'price' => 100, 'stock' => 5, 'status' => 'active']);
        Message::create(['sender_id' => $buyer->id, 'receiver_id' => $seller->id, 'body' => 'Is this available?', 'product_id' => $product->id, 'read' => false]);

        $this->actingAs($buyer)->get('/buyer/chat?user='.$seller->id)
            ->assertOk()
            ->assertSee('action="/buyer/chat/send" class="buyer-chat-row-3" data-no-confirm', false)
            ->assertSee('data-clear-product-context', false)
            ->assertSee('Asking about');

        // After the buyer clears the context and sends a plain message, it does not come back.
        $this->post('/buyer/chat/send', ['receiver_id' => $seller->id, 'body' => 'Also, do you ship to Cebu?'])
            ->assertRedirect('/buyer/chat?user='.$seller->id);
        $this->get('/buyer/chat?user='.$seller->id)->assertOk()->assertDontSee('Asking about');
    }

    public function test_signed_in_users_reopening_the_site_land_on_their_dashboard(): void
    {
        $this->get('/')->assertOk();
        $this->actingAs($this->user('buyer'))->get('/')->assertRedirect('/buyer/dashboard');
    }

    public function test_seller_reopening_the_site_lands_on_the_seller_dashboard(): void
    {
        $this->actingAs($this->user('seller'))->get('/')->assertRedirect('/seller/dashboard');
    }

    public function test_an_upload_the_server_refused_gets_a_clear_message(): void
    {
        // An upload PHP rejected (e.g. over upload_max_filesize) reaches Laravel with an error code.
        $refused = new UploadedFile(__FILE__, 'id.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->from('/register')->post('/register', ['role' => 'buyer', 'id_upload' => $refused])
            ->assertSessionHasErrors(['id_upload' => 'This file could not be uploaded. Please choose a file of 5 MB or smaller.']);
    }
}
