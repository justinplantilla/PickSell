<?php

namespace Tests\Feature\BuyerApi;

use App\Models\FinancialTransaction;
use App\Models\OrderStatusHistory;
use App\Models\ProductReview;
use App\Notifications\BuyerOrderUpdate;
use App\Notifications\SellerDeliveryReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A delivered order the buyer doesn't confirm is marked received 5 days after delivery (OrderReceipt). */
class AutoConfirmReceivedTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_orders_are_confirmed_five_days_after_delivery_if_the_buyer_did_not(): void
    {
        Notification::fake();
        $this->travelTo(now()->startOfHour());
        $seller = $this->seller();
        $buyer = $this->buyer();
        $lamp = $this->product($seller);
        $due = $this->order($buyer, $lamp, 'delivered', ['delivered_at' => now()->subDays(5), 'rating' => 4, 'seller_rating' => 5, 'delivery_rating' => 5, 'feedback' => 'Rated early']);
        $recent = $this->order($buyer, $lamp, 'delivered', ['delivered_at' => now()->subDays(4)]);
        $shipping = $this->order($buyer, $lamp, 'out_for_delivery');

        // The buyer sees when it will happen, in the app and on the website.
        $this->actingAsBuyer($buyer);
        $this->getJson("/api/mobile/buyer/orders/{$recent->id}")->assertOk()
            ->assertJsonPath('order.auto_confirm_at', now()->addDay()->toIso8601String());
        $this->getJson("/api/mobile/buyer/orders/{$shipping->id}")->assertJsonPath('order.auto_confirm_at', null);
        $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee('We’ll confirm it for you on '.now()->addDay()->format('M d, Y'), false);

        $this->artisan('orders:auto-confirm-received')->expectsOutput('Confirmed 1 delivered orders.')->assertSuccessful();

        $this->assertSame(['completed', 'delivered', 'out_for_delivery'], [$due->fresh()->status, $recent->fresh()->status, $shipping->fresh()->status]);
        $this->assertSame('Automatically confirmed 5 days after delivery', $due->fresh()->tracking_status);
        $this->assertSame('system', OrderStatusHistory::where('order_id', $due->id)->where('to_status', 'completed')->sole()->source);
        $this->assertSame(3, FinancialTransaction::where('reference_type', 'order')->where('reference_id', $due->id)->count(), 'the seller is paid');
        $this->assertSame('Rated early', ProductReview::sole()->body, 'an earlier rating is published');
        Notification::assertSentTo($buyer, BuyerOrderUpdate::class, fn ($n) => str_contains($n->message, 'marked received 5 days after delivery'));
        Notification::assertSentTo($seller, SellerDeliveryReceived::class);

        // Safe to run again; the next order follows a day later.
        $this->artisan('orders:auto-confirm-received')->expectsOutput('Confirmed 0 delivered orders.');
        $this->travel(1)->days();
        $this->artisan('orders:auto-confirm-received')->expectsOutput('Confirmed 1 delivered orders.');
        $this->assertSame('completed', $recent->fresh()->status);
    }

    public function test_a_buyer_who_confirms_first_is_not_confirmed_again(): void
    {
        $buyer = $this->buyer();
        $order = $this->order($buyer, $this->product($this->seller()), 'delivered', ['delivered_at' => now()->subDays(6)]);
        $this->actingAsBuyer($buyer);
        $this->postJson("/api/mobile/buyer/orders/{$order->id}/received")->assertOk();

        $this->artisan('orders:auto-confirm-received')->expectsOutput('Confirmed 0 delivered orders.');
        $this->assertSame(['completed', 'Buyer confirmed receipt'], [$order->fresh()->status, $order->fresh()->tracking_status]);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('to_status', 'completed')->count());
    }
}
