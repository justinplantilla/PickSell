<?php

namespace Tests\Feature\BuyerApi;

use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * The website checkout's voucher picker (CheckoutVouchers) through the quote and place-order
 * endpoints: store vouchers (one per store) and a PickSell voucher, or a typed code.
 */
class CheckoutVouchersTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function voucher(array $attrs): Voucher
    {
        return Voucher::create($attrs + ['title' => 'Promo', 'type' => Voucher::FIXED, 'amount' => 50, 'per_buyer_limit' => 1, 'is_active' => true]);
    }

    public function test_the_quote_lists_store_and_picksell_vouchers_with_what_each_saves(): void
    {
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $lamp = $this->cartLine($buyer, $this->product($seller, ['name' => 'Lamp', 'price' => 600]));
        $mug = $this->cartLine($buyer, $this->product($seller, ['name' => 'Mug', 'price' => 400]));
        $save100 = $this->voucher(['seller_id' => $seller->id, 'code' => 'SAVE100', 'title' => '₱100 off', 'amount' => 100, 'min_spend' => 500]);
        $big = $this->voucher(['seller_id' => $seller->id, 'code' => 'BIG', 'min_spend' => 5000]);
        $tenPct = $this->voucher(['code' => 'PICK10', 'title' => '10% off', 'type' => Voucher::PERCENT, 'amount' => 10, 'max_discount' => 50]);

        // Nothing picked: every option with its saving or why it can't be used, best first.
        $v = $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$lamp->id, $mug->id]])->assertOk()->json('vouchers');
        $this->assertSame($seller->id, $v['stores'][0]['seller_id']);
        $this->assertSame([
            'id' => $save100->id, 'code' => 'SAVE100', 'title' => '₱100 off', 'summary' => '₱100 off · min. spend ₱500',
            'type' => 'fixed', 'discount' => 100, 'problem' => null, 'ends_at' => null,
        ], $v['stores'][0]['options'][0]);
        $this->assertSame([$big->id, 'Spend ₱4,000.00 more to use this voucher.'], [$v['stores'][0]['options'][1]['id'], $v['stores'][0]['options'][1]['problem']]);
        $this->assertSame([$tenPct->id, 50, '10% off up to ₱50'], [$v['platform'][0]['id'], $v['platform'][0]['discount'], $v['platform'][0]['summary']]);
        $this->assertSame(['stores' => [], 'platform' => null], $v['picked']);
        $this->assertSame([0, null], [$v['total'], $v['error']]);

        // Picking both: ₱100 off the store, then 10% of the ₱900 left, capped at ₱50.
        $v = $this->postJson('/api/mobile/buyer/checkout/quote', [
            'item_ids' => [$lamp->id, $mug->id], 'store_vouchers' => [$seller->id => $save100->id], 'platform_voucher' => $tenPct->id,
        ])->assertOk()->json('vouchers');
        $this->assertSame(['stores' => [(string) $seller->id => $save100->id], 'platform' => $tenPct->id], $v['picked']);
        $this->assertSame([(string) $seller->id => 100], $v['store_savings']);
        $this->assertSame([50, 150], [$v['platform_saving'], $v['total']]);

        // A typed code (any case) picks the voucher it belongs to; an unknown one says so.
        $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$lamp->id], 'code' => 'pick10'])
            ->assertJsonPath('vouchers.picked.platform', $tenPct->id);
        $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$lamp->id], 'code' => 'NOPE'])
            ->assertJsonPath('vouchers.error', 'NOPE is not a voucher for these items.');
        // Picking one that can't be used drops it and explains why.
        $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$lamp->id], 'store_vouchers' => [$seller->id => $big->id]])
            ->assertJsonPath('vouchers.picked.stores', [])
            ->assertJsonPath('vouchers.error', 'BIG: Spend ₱4,400.00 more to use this voucher.');
    }

    public function test_placing_the_order_applies_the_vouchers_like_the_website(): void
    {
        config()->set('app.platform_commission_rate', 10);
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $lamp = $this->cartLine($buyer, $this->product($seller, ['name' => 'Lamp', 'price' => 600]));
        $mug = $this->cartLine($buyer, $this->product($seller, ['name' => 'Mug', 'price' => 400]));
        $save100 = $this->voucher(['seller_id' => $seller->id, 'code' => 'SAVE100', 'amount' => 100, 'min_spend' => 500]);
        $pick50 = $this->voucher(['code' => 'PICK50', 'amount' => 50]);
        $quote = $this->postJson('/api/mobile/buyer/checkout/quote', [
            'item_ids' => [$lamp->id, $mug->id], 'store_vouchers' => [$seller->id => $save100->id], 'platform_voucher' => $pick50->id,
        ])->json();
        $shipping = $quote['quote']['totals']['shipping_total'];

        $placed = $this->postJson('/api/mobile/buyer/checkout', [
            'item_ids' => [$lamp->id, $mug->id], 'store_vouchers' => [$seller->id => $save100->id], 'platform_voucher' => $pick50->id,
        ])->assertCreated()->json();

        // ₱100 spread 60/40 over the lines; PickSell's ₱50 comes off the parcel.
        $orders = Order::orderBy('id')->get();
        $this->assertEquals([540, 360], $orders->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertEquals([60, 40], $orders->pluck('store_discount')->map(fn ($a) => (float) $a)->all());
        $this->assertEquals(50, (float) OrderShipment::sole()->platform_discount);
        $this->assertSame(2, VoucherRedemption::count());
        $this->assertEquals(1000 - 100 - 50 + $shipping, $placed['total'], 'what the rider collects');

        // The order page's breakdown.
        $this->getJson("/api/mobile/buyer/orders/{$orders[0]->id}")->assertOk()
            ->assertJsonPath('order.store_discount', 60)
            ->assertJsonPath('order.platform_discount', 50)
            ->assertJsonPath('order.parcel_items', 2)
            ->assertJsonPath('order.parcel_subtotal', 900)
            ->assertJsonPath('order.parcel_total', 900 - 50 + $shipping);
    }

    public function test_a_voucher_that_can_no_longer_be_used_stops_the_order(): void
    {
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $voucher = $this->voucher(['seller_id' => $seller->id, 'code' => 'ONCE']);
        $first = $this->cartLine($buyer, $this->product($seller));
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$first->id], 'store_vouchers' => [$seller->id => $voucher->id]])->assertCreated();

        $again = $this->cartLine($buyer, $this->product($seller, ['name' => 'Second Lamp']));
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$again->id], 'store_vouchers' => [$seller->id => $voucher->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.vouchers.0', 'ONCE: You have already used this voucher.');
        $this->assertSame(1, Order::count());

        // Without vouchers nothing changes for older app versions.
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$again->id]])->assertCreated();
    }
}
