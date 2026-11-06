<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class VouchersTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function voucher(array $attrs): Voucher
    {
        return Voucher::create($attrs + ['title' => 'Promo', 'type' => Voucher::FIXED, 'amount' => 50, 'per_buyer_limit' => 1, 'is_active' => true]);
    }

    public function test_sellers_manage_their_store_vouchers(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->post(route('seller.vouchers.store'), [
            'code' => 'save50', 'title' => '₱50 off', 'type' => 'fixed', 'amount' => 50, 'min_spend' => 500, 'per_buyer_limit' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $voucher = Voucher::sole();
        $this->assertSame(['SAVE50', $seller->id, '500.00'], [$voucher->code, $voucher->seller_id, $voucher->min_spend]);

        $this->actingAs($seller)->post(route('seller.vouchers.store'), ['code' => 'SAVE50', 'title' => 'Again', 'type' => 'fixed', 'amount' => 10, 'per_buyer_limit' => 1])
            ->assertSessionHasErrors('code');
        $this->actingAs($seller)->post(route('seller.vouchers.store'), ['code' => 'FREE', 'title' => 'Free', 'type' => 'free_shipping', 'per_buyer_limit' => 1])
            ->assertSessionHasErrors('type');
        $this->actingAs($seller)->post(route('seller.vouchers.store'), ['code' => 'HALF', 'title' => 'Big', 'type' => 'percent', 'amount' => 150, 'per_buyer_limit' => 1])
            ->assertSessionHasErrors('amount');

        $this->actingAs($seller)->get(route('seller.vouchers'))->assertOk()->assertSee('SAVE50')->assertSee('₱50 off · min. spend ₱500');
        $this->actingAs($this->seller())->delete(route('seller.vouchers.destroy', $voucher))->assertNotFound();
        $this->actingAs($seller)->delete(route('seller.vouchers.destroy', $voucher))->assertRedirect();
        $this->assertModelMissing($voucher);
    }

    public function test_a_store_voucher_comes_off_the_sellers_orders(): void
    {
        config()->set('app.platform_commission_rate', 10);
        $seller = $this->seller();
        $buyer = $this->buyer();
        $lamp = $this->product($seller, ['name' => 'Lamp', 'price' => 600]);
        $mug = $this->product($seller, ['name' => 'Mug', 'price' => 400]);
        $line1 = $this->cartLine($buyer, $lamp);
        $line2 = $this->cartLine($buyer, $mug);
        $voucher = $this->voucher(['seller_id' => $seller->id, 'code' => 'SAVE100', 'amount' => 100, 'min_spend' => 500]);

        // A typed code (any case) picks it, and checkout shows the saving.
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line1->id, $line2->id], 'code' => 'save100']))->assertOk()
            ->assertSee('name="store_vouchers['.$seller->id.']" value="'.$voucher->id.'"', false)
            ->assertSeeInOrder(['Store vouchers', '−₱100.00']);

        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line1->id, $line2->id], 'store_vouchers' => [$seller->id => $voucher->id]])
            ->assertRedirect(route('buyer.orders'));

        // ₱100 spread 60/40 over the ₱600 and ₱400 lines; commission is on what the seller gets.
        $orders = Order::orderBy('id')->get();
        $this->assertEquals([540, 360], $orders->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertEquals([60, 40], $orders->pluck('store_discount')->map(fn ($a) => (float) $a)->all());
        $this->assertEquals(54, (float) $orders[0]->commission);
        $this->assertEquals(100, (float) OrderShipment::sole()->store_discount);
        $this->assertSame(1, VoucherRedemption::count());

        // One use per buyer.
        $again = $this->cartLine($buyer, $lamp);
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$again->id], 'store_vouchers' => [$seller->id => $voucher->id]])
            ->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('vouchers');
    }

    public function test_store_vouchers_need_their_minimum_spend_and_their_own_store(): void
    {
        $seller = $this->seller();
        $other = $this->seller();
        $buyer = $this->buyer();
        $line = $this->cartLine($buyer, $this->product($seller, ['price' => 300]));
        $voucher = $this->voucher(['seller_id' => $seller->id, 'code' => 'BIG', 'min_spend' => 500]);
        $elsewhere = $this->voucher(['seller_id' => $other->id, 'code' => 'OTHER']);

        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id]]))->assertOk()->assertSee('Spend ₱200.00 more to use this voucher.');
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id], 'code' => 'OTHER']))->assertOk()->assertSee('OTHER is not a voucher for these items.');
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id], 'store_vouchers' => [$seller->id => $voucher->id]])->assertSessionHasErrors('vouchers');
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id], 'store_vouchers' => [$seller->id => $elsewhere->id]])->assertSessionHasErrors('vouchers');
        $this->assertSame(0, Order::count());
    }

    public function test_pickSell_vouchers_lower_what_the_rider_collects_not_the_sellers_sale(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $line = $this->cartLine($buyer, $this->product($seller, ['price' => 1000]));
        $voucher = $this->voucher(['seller_id' => null, 'code' => 'PICK200', 'amount' => 200]);

        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id]]))->assertOk()->assertSee('PickSell vouchers')->assertSee('PICK200');
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id], 'platform_voucher' => $voucher->id])->assertRedirect(route('buyer.orders'));

        $this->assertEquals(1000, (float) Order::sole()->amount, 'the seller is paid in full');
        $shipment = OrderShipment::sole();
        $this->assertEquals(200, (float) $shipment->platform_discount);
        $this->actingAs($buyer)->get(route('buyer.orders'))->assertSeeInOrder(['PickSell voucher', '−₱200.00', 'Total', '₱'.number_format(1000 + (float) $shipment->delivery_fee - 200, 2)]);
    }

    public function test_free_shipping_covers_the_delivery_fees(): void
    {
        $buyer = $this->buyer();
        $line1 = $this->cartLine($buyer, $this->product($this->seller()));
        $line2 = $this->cartLine($buyer, $this->product($this->seller(['province' => 'Metro Manila'])));
        $voucher = $this->voucher(['seller_id' => null, 'code' => 'FREESHIP', 'type' => Voucher::FREE_SHIPPING, 'amount' => 0]);

        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line1->id, $line2->id], 'platform_voucher' => $voucher->id])->assertRedirect(route('buyer.orders'));

        OrderShipment::all()->each(fn ($s) => $this->assertEquals((float) $s->delivery_fee, (float) $s->shipping_discount));
        $this->assertSame(0, CartItem::count());
    }
}
