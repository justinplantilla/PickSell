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
        $save100 = $this->voucher(['seller_id' => $seller->id, 'code' => 'SAVE100', 'title' => 'Misleading ₱999 off', 'amount' => 100, 'min_spend' => 500]);
        $big = $this->voucher(['seller_id' => $seller->id, 'code' => 'BIG', 'min_spend' => 5000]);
        $tenPct = $this->voucher(['code' => 'PICK10', 'title' => '10% off', 'type' => Voucher::PERCENT, 'amount' => 10, 'max_discount' => 50]);

        // Nothing picked: every option with its saving or why it can't be used, best first.
        $v = $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$lamp->id, $mug->id]])->assertOk()->json('vouchers');
        $this->assertSame($seller->id, $v['stores'][0]['seller_id']);
        $this->assertSame([
            'id' => $save100->id, 'code' => 'SAVE100', 'title' => '₱100 off', 'summary' => 'Minimum spend ₱500 · Once per buyer',
            'type' => 'fixed', 'discount' => 100, 'problem' => null, 'ends_at' => null, 'used_percent' => null,
            'uses_left' => 1, 'recommended' => true, 'min_spend' => 500, 'starts_at' => null, 'upcoming' => false,
        ], $v['stores'][0]['options'][0]);
        $this->assertSame([$big->id, 'Spend ₱4,000.00 more to use this voucher.'], [$v['stores'][0]['options'][1]['id'], $v['stores'][0]['options'][1]['problem']]);
        $this->assertSame([$tenPct->id, 50, '10% off up to ₱50'], [$v['platform'][0]['id'], $v['platform'][0]['discount'], $v['platform'][0]['title']]);
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

    public function test_buyers_see_how_much_of_a_limited_voucher_is_used(): void
    {
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $lamp = $this->cartLine($buyer, $this->product($seller, ['name' => 'Lamp', 'price' => 600]));
        $limited = $this->voucher(['code' => 'FIRST20', 'usage_limit' => 20]);
        foreach (range(1, 4) as $_) {
            VoucherRedemption::forceCreate(['voucher_id' => $limited->id, 'buyer_id' => $this->seller()->id, 'amount' => 50]);
        }

        $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$lamp->id]])
            ->assertOk()->assertJsonPath('vouchers.platform.0.used_percent', 20);
        $this->get(route('buyer.checkout.start', ['item_ids' => [$lamp->id]]))
            ->assertOk()->assertSee('20% used')->assertSee('aria-valuenow="20"', false)
            ->assertSee('Recommended')->assertSee('>x1<', false)->assertSee('No minimum spend');
    }

    public function test_scheduled_vouchers_are_shown_ahead_but_cannot_be_picked_yet(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 10:00:00'));
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $lamp = $this->cartLine($buyer, $this->product($seller, ['name' => 'Lamp', 'price' => 600]));
        $soon = $this->voucher(['code' => 'PAYDAY', 'starts_at' => '2026-11-20 00:00', 'ends_at' => '2026-11-30 23:59']);
        $this->voucher(['code' => 'OFFNOW', 'starts_at' => '2026-11-20 00:00', 'is_active' => false]);

        $v = $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$lamp->id], 'platform_voucher' => $soon->id])->assertOk()->json('vouchers');
        $this->assertCount(1, $v['platform'], 'a switched-off voucher stays hidden');
        $this->assertSame([true, false, 'Starts Nov 20.'], [$v['platform'][0]['upcoming'], $v['platform'][0]['recommended'], $v['platform'][0]['problem']]);
        $this->assertNull($v['picked']['platform'], 'not usable before it starts');

        $this->get(route('buyer.checkout.start', ['item_ids' => [$lamp->id]]))
            ->assertOk()->assertSee('Starts Nov 20')->assertSee("Can't use", false)
            ->assertSee('voucher-ticket-cover', false)->assertSee('data-voucher-blocked="Starts Nov 20."', false);
    }

    public function test_vouchers_that_cannot_be_used_for_these_items_say_so_per_store(): void
    {
        $tote = $this->seller();
        $mugs = $this->seller();
        $buyer = $this->actingAsBuyer();
        $bag = $this->cartLine($buyer, $this->product($tote, ['name' => 'Bag', 'price' => 300]));
        $mug = $this->cartLine($buyer, $this->product($mugs, ['name' => 'Mug', 'price' => 900]));
        $this->voucher(['seller_id' => $tote->id, 'code' => 'TOTE50']);
        $this->voucher(['seller_id' => $tote->id, 'code' => 'TOTEBIG', 'min_spend' => 500]);

        $page = $this->get(route('buyer.checkout.start', ['item_ids' => [$bag->id, $mug->id]]))->assertOk();
        $page->assertSee("Can't use", false)->assertSee('Spend ₱200.00 more to use this voucher.')
            ->assertSee('for '.($tote->business_name ?? $tote->full_name).' items only', false)
            ->assertSee('aria-disabled="true"', false)->assertSee('voucher-ticket-cover', false)->assertSee('data-voucher-blocked="Spend ₱200.00 more to use this voucher."', false);
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
