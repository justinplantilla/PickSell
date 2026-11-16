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

    public function test_buyers_see_actual_terms_instead_of_a_misleading_promo_name(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $line = $this->cartLine($buyer, $this->product($seller));
        $voucher = $this->voucher([
            'seller_id' => $seller->id, 'code' => 'WEEKEND', 'title' => 'Guaranteed 100% FREE',
            'amount' => 10.25, 'min_spend' => 500.50, 'usage_limit' => 25,
        ]);

        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id]]))
            ->assertOk()->assertDontSee('Guaranteed 100% FREE')
            ->assertSee('₱10.25 off')->assertSee('Min. spend ₱500.5')->assertSee('0% used');

        $this->assertSame('Guaranteed 100% FREE', $voucher->fresh()->title);
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
        $this->assertEquals([540, 360], $orders->first()->items->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertEquals([60, 40], $orders->first()->items->pluck('store_discount')->map(fn ($a) => (float) $a)->all());
        $this->assertEquals(90, (float) $orders[0]->commission);
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

    public function test_each_store_takes_a_store_voucher_or_a_product_voucher_on_its_own_card(): void
    {
        $a = $this->seller(['business_name' => 'Lamp Shop']);
        $b = $this->seller(['business_name' => 'Mug Shop']);
        $buyer = $this->buyer();
        $lamp = $this->cartLine($buyer, $this->product($a, ['price' => 1000, 'voucher_code' => 'Lamp10', 'voucher_discount' => 10]));
        $mug = $this->cartLine($buyer, $this->product($b, ['price' => 500]));
        $storeA = $this->voucher(['seller_id' => $a->id, 'code' => 'LAMPSHOP', 'amount' => 30]);
        $storeB = $this->voucher(['seller_id' => $b->id, 'code' => 'MUGSHOP', 'amount' => 50]);
        $ids = [$lamp->id, $mug->id];

        // Each store's card lists its own vouchers; the bottom card is PickSell's only.
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => $ids]))->assertOk()
            ->assertSee('id="store-vouchers-'.$a->id.'"', false)->assertSee('id="store-vouchers-'.$b->id.'"', false)
            ->assertSee('Lamp10')->assertSee('Have a PickSell voucher code?')->assertDontSee('Product vouchers</p>', false);

        // A product code typed on its store's card picks it (case-sensitive) and drops that store's store voucher.
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => $ids, 'store_voucher' => [$a->id => $storeA->id, $b->id => $storeB->id], 'code' => 'Lamp10', 'code_for' => $a->id]))
            ->assertOk()->assertSee('Product voucher <code>Lamp10</code>', false)->assertSee('-&#8369;100.00', false)->assertSee('name="product_vouchers['.$a->id.']" value="Lamp10"', false)
            ->assertDontSee('name="store_vouchers['.$a->id.']"', false)->assertSee('name="store_vouchers['.$b->id.']"', false);
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => $ids, 'code' => 'Lamp10', 'code_for' => $b->id]))
            ->assertOk()->assertSee('Lamp10 is not a voucher for this store.');
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => $ids, 'code' => 'MUGSHOP', 'code_for' => 'platform']))
            ->assertOk()->assertSee('MUGSHOP is not a PickSell voucher.');

        // Placing with both for one store is refused; a product voucher on A and a store voucher on B is fine.
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => $ids, 'product_vouchers' => [$a->id => 'Lamp10'], 'store_vouchers' => [$a->id => $storeA->id]])
            ->assertSessionHasErrors('vouchers');
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => $ids, 'product_vouchers' => [$a->id => 'Lamp10'], 'store_vouchers' => [$b->id => $storeB->id]])
            ->assertRedirect(route('buyer.orders'));
        $orders = Order::all()->keyBy('seller_id');
        $this->assertEquals(900, (float) $orders[$a->id]->amount);
        $this->assertEquals(450, (float) $orders[$b->id]->amount);
    }

    public function test_pickSell_vouchers_lower_what_the_rider_collects_not_the_sellers_sale(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $line = $this->cartLine($buyer, $this->product($seller, ['price' => 1000]));
        $voucher = $this->voucher(['seller_id' => null, 'code' => 'PICK200', 'amount' => 200]);

        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id]]))->assertOk()->assertSee('PickSell Vouchers')->assertSee('PICK200');
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
