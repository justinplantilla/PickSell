<?php

namespace Tests\Feature\BuyerApi;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Voucher;
use App\Services\OrderPlacement;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class StoreCheckoutTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_three_stores_create_three_orders_with_five_items_and_independent_payments(): void
    {
        $buyer = $this->actingAsBuyer();
        $stores = collect([$this->seller(), $this->seller(), $this->seller()]);
        $lines = collect();
        foreach ([0, 0, 1, 2, 2] as $i => $store) {
            $lines->push($this->cartLine($buyer, $this->product($stores[$store], ['name' => "Item {$i}", 'price' => 100 + $i])));
        }
        $unselected = $this->cartLine($buyer, $this->product($stores[0]));
        $data = ['item_ids' => $lines->pluck('id')->all(), 'checkout_key' => 'three-store-test'];
        $quote = $this->postJson('/api/mobile/buyer/checkout/quote', $data)->assertOk()->json('quote');
        $response = $this->postJson('/api/mobile/buyer/checkout', $data)->assertCreated()->assertJsonCount(3, 'orders');
        $this->assertDatabaseCount('orders', 3);
        $this->assertDatabaseCount('order_items', 5);
        $this->assertDatabaseCount('order_payments', 3);
        $this->assertDatabaseCount('order_shipments', 3);
        $this->assertDatabaseHas('cart_items', ['id' => $unselected->id]);
        $this->assertEquals(collect($quote['groups'])->sum('total'), OrderPayment::sum('amount_due'));
        foreach (Order::with('items', 'payment')->get() as $order) {
            $this->assertEquals($order->amount, $order->items->sum('amount'));
            $this->assertSame('unpaid', $order->payment->status);
            $this->assertTrue($order->items->every(fn ($item) => $item->product->seller_id === $order->seller_id));
        }
        $this->postJson('/api/mobile/buyer/checkout', $data)->assertCreated()->assertJsonPath('checkout_ref', $response->json('checkout_ref'));
        $this->assertDatabaseCount('orders', 3);
        $this->assertDatabaseCount('order_payments', 3);
        $this->getJson('/api/mobile/buyer/orders')->assertOk()->assertJsonCount(3, 'orders');
    }

    public function test_voucher_quote_matches_saved_store_totals_with_product_and_platform_discounts(): void
    {
        $buyer = $this->actingAsBuyer();
        $a = $this->seller(); $b = $this->seller();
        $one = $this->cartLine($buyer, $this->product($a, ['price' => 600, 'voucher_code' => 'ITEM10', 'voucher_discount' => 10]));
        $two = $this->cartLine($buyer, $this->product($a, ['price' => 400]));
        $three = $this->cartLine($buyer, $this->product($b, ['price' => 250]));
        $store = Voucher::create(['seller_id' => $a->id, 'code' => 'STORE10', 'title' => '10%', 'type' => 'percent', 'amount' => 10, 'is_active' => true, 'per_buyer_limit' => 1]);
        $platform = Voucher::create(['code' => 'PLATFORM', 'title' => '50 off', 'type' => 'fixed', 'amount' => 50, 'is_active' => true, 'per_buyer_limit' => 1]);
        $data = ['item_ids' => [$one->id, $two->id, $three->id], 'voucher_code' => 'ITEM10', 'store_vouchers' => [$a->id => $store->id], 'platform_voucher' => $platform->id];
        $quote = $this->postJson('/api/mobile/buyer/checkout/quote', $data)->assertOk()->json('quote');
        $this->postJson('/api/mobile/buyer/checkout', $data)->assertCreated();
        $orders = Order::with('shipment', 'payment')->get()->keyBy('seller_id');
        $this->assertEquals(846, $orders[$a->id]->amount);
        $this->assertEquals(250, $orders[$b->id]->amount);
        $this->assertEquals(94, $orders[$a->id]->store_discount);
        foreach ($quote['groups'] as $group) $this->assertEquals($group['total'], $orders[$group['seller']['id']]->payment->amount_due);
        $this->assertDatabaseCount('voucher_redemptions', 2);
        $this->postJson('/api/mobile/buyer/checkout', $data)->assertCreated();
        $this->assertDatabaseCount('voucher_redemptions', 2);
    }

    public function test_cross_store_expired_and_used_vouchers_cannot_create_partial_orders(): void
    {
        $buyer = $this->actingAsBuyer(); $a = $this->seller(); $b = $this->seller();
        $one = $this->cartLine($buyer, $this->product($a));
        $two = $this->cartLine($buyer, $this->product($b));
        $voucher = Voucher::create(['seller_id' => $a->id, 'code' => 'ONLYA', 'title' => 'A', 'type' => 'fixed', 'amount' => 10, 'is_active' => true, 'per_buyer_limit' => 1]);
        $ids = [$one->id, $two->id];
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => $ids, 'store_vouchers' => [$b->id => $voucher->id]])->assertUnprocessable()->assertJsonValidationErrors('vouchers');
        $voucher->update(['ends_at' => now()->subDay()]);
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => $ids, 'store_vouchers' => [$a->id => $voucher->id]])->assertUnprocessable();
        $voucher->update(['ends_at' => null]);
        $voucher->redemptions()->create(['buyer_id' => $buyer->id, 'amount' => 10]);
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => $ids, 'store_vouchers' => [$a->id => $voucher->id]])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('order_shipments', 0);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertEquals(5, $one->product->fresh()->stock);
    }

    public function test_failure_after_an_order_is_created_rolls_back_every_store_and_cart_mutation(): void
    {
        $buyer = $this->actingAsBuyer();
        $one = $this->cartLine($buyer, $this->product($this->seller()));
        $two = $this->cartLine($buyer, $this->product($this->seller()));
        $fail = true;
        OrderItem::creating(function () use (&$fail) { if ($fail) throw new \RuntimeException('Simulated item failure'); });
        try {
            app(OrderPlacement::class)->place($buyer, [$one->id, $two->id], null);
            $this->fail('Expected transaction failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated item failure', $e->getMessage());
        } finally { $fail = false; }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('order_shipments', 0);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertEquals(5, $one->product->fresh()->stock);
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$one->id, $two->id]])->assertCreated();
    }

    public function test_cancelling_a_store_order_restores_all_its_items_only(): void
    {
        $buyer = $this->actingAsBuyer(); $seller = $this->seller();
        $one = $this->cartLine($buyer, $this->product($seller), 2);
        $two = $this->cartLine($buyer, $this->product($seller), 1);
        $other = $this->cartLine($buyer, $this->product($this->seller()), 1);
        $orders = app(OrderPlacement::class)->place($buyer, [$one->id, $two->id, $other->id], null)->keyBy('seller_id');
        app(OrderCancellationService::class)->cancel($orders[$seller->id], $buyer, 'buyer');
        $this->assertEquals(5, $one->product->fresh()->stock);
        $this->assertEquals(5, $two->product->fresh()->stock);
        $this->assertEquals(4, $other->product->fresh()->stock);
        $this->assertSame('cancelled', $orders[$seller->id]->payment->status);
        $this->assertEquals(1, OrderPayment::where('status', 'unpaid')->count());
    }

    public function test_checkout_key_cannot_be_reused_for_different_items_and_foreign_lines_are_rejected(): void
    {
        $buyer = $this->actingAsBuyer();
        $one = $this->cartLine($buyer, $this->product($this->seller()));
        $other = $this->cartLine($this->buyer(), $this->product($this->seller()));
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$one->id, $other->id]])->assertUnprocessable();
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$one->id], 'checkout_key' => 'same-key'])->assertCreated();
        $next = $this->cartLine($buyer, $one->product);
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$next->id], 'checkout_key' => 'same-key'])->assertUnprocessable()->assertJsonValidationErrors('checkout_key');
        $this->assertDatabaseCount('orders', 1);
    }
    public function test_buyer_and_seller_pages_list_a_store_orders_items_once(): void
    {
        $buyer = $this->actingAsBuyer(); $seller = $this->seller();
        $one = $this->cartLine($buyer, $this->product($seller, ['name' => 'Desk Lamp']));
        $two = $this->cartLine($buyer, $this->product($seller, ['name' => 'Lamp Shade']));
        $order = app(OrderPlacement::class)->place($buyer, [$one->id, $two->id], null)->sole();
        $order->update(['status' => 'delivered', 'delivered_at' => now()]);

        $html = $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()->getContent();
        $this->assertStringNotContainsString('@endif', $html);
        $this->assertSame(1, substr_count($html, 'aria-label="Order items"'));
        $this->assertSame(1, substr_count($html, 'name="order_item_id"'));

        $html = $this->actingAs($seller)->get('/seller/orders')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'aria-label="Order items"'));
        $this->assertStringNotContainsString('Desk Lamp, Lamp Shade', $html);
        $this->actingAs($seller)->get("/seller/orders/{$order->id}")->assertOk()->assertSee('Lamp Shade')->assertDontSee('Desk Lamp, Lamp Shade');
    }

    public function test_item_returns_and_reordering_use_the_selected_items_own_price_and_quantity(): void
    {
        $buyer = $this->actingAsBuyer(); $seller = $this->seller();
        $one = $this->cartLine($buyer, $this->product($seller, ['price' => 800]), 2);
        $two = $this->cartLine($buyer, $this->product($seller, ['price' => 100]), 1);
        $order = app(OrderPlacement::class)->place($buyer, [$one->id, $two->id], null)->sole();
        $order->update(['status' => 'delivered']);
        $item = $order->items->firstWhere('product_id', $two->product_id);
        $return = app(\App\Services\Returns\ReturnFlow::class)->request($order, $buyer, [
            'order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'damaged', 'details' => 'Broken on arrival',
            'gcash_number' => '09171234567', 'gcash_name' => 'Test Buyer',
        ], []);
        $this->assertEquals(100, $return->refund_amount);
        $this->assertSame($item->id, $return->order_item_id);
        $this->postJson("/api/mobile/buyer/orders/{$order->id}/reorder")->assertOk();
        $this->assertDatabaseHas('cart_items', ['product_id' => $one->product_id, 'quantity' => 2]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $two->product_id, 'quantity' => 1]);
        $this->assertEquals(2, \App\Models\Product::whereKey($one->product_id)->withSoldUnits()->first()->sold_count);
        $this->assertEquals(1, \App\Models\Product::whereKey($two->product_id)->withSoldUnits()->first()->sold_count);
    }

}
