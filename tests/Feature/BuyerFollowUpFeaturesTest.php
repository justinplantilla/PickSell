<?php

namespace Tests\Feature;

use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Notifications\FollowedStoreNewProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class BuyerFollowUpFeaturesTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function shipment(int $buyerId, int $sellerId, array $extra = []): OrderShipment
    {
        return OrderShipment::create($extra + [
            'reference' => 'SHP-'.strtoupper(uniqid()), 'buyer_id' => $buyerId, 'seller_id' => $sellerId,
            'destination_region' => 'VISAYAS', 'price_source' => 'ORIGIN_DESTINATION', 'parcel_class' => 'STANDARD', 'chargeable_weight_kg' => 1,
            'delivery_fee' => 95, 'rider_pay' => 50, 'platform_commission' => 10, 'logistics_share' => 35, 'delivery_commission_rate' => 10, 'rates_version' => 'test',
        ]);
    }

    public function test_order_details_show_the_real_shipping_fee(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $shipment = $this->shipment($buyer->id, $seller->id);
        $this->order($buyer, $this->product($seller), 'preparing', ['amount' => 500, 'quantity' => 1, 'order_shipment_id' => $shipment->id]);

        $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSeeInOrder(['Shipping fee', '₱95.00', 'Total', '₱595.00']);

        // A second item in the same parcel shares the fee; the parcel total is what the rider collects.
        $this->order($buyer, $this->product($seller), 'preparing', ['amount' => 300, 'quantity' => 1, 'order_shipment_id' => $shipment->id]);
        $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee('for this parcel of 2 items')->assertSeeInOrder(['Parcel total (COD)', '₱895.00']);
    }

    public function test_the_whole_cart_shows_on_one_page(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        foreach (range(1, 22) as $n) {
            $this->cartLine($buyer, $this->product($seller, ['name' => "Item {$n}"]));
        }

        $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk()->assertSee('My Cart (22 items)')
            ->assertSee('Item 1')->assertSee('Item 22');
    }

    public function test_a_saved_address_can_become_the_default_without_moving_placed_orders(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer(['delivery_lat' => 10.33, 'delivery_lng' => 123.9]);
        $office = BuyerAddress::create(['user_id' => $buyer->id, 'label' => 'Office', 'recipient_name' => 'Ana', 'contact_no' => '09170000000',
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Diliman', 'street' => 'Katipunan Ave',
            'delivery_lat' => 14.65, 'delivery_lng' => 121.07]);
        // An older shipment to the old default, from before shipments kept a copy of the address.
        $oldOrder = $this->shipment($buyer->id, $seller->id);

        $this->actingAs($buyer)->post(route('buyer.addresses.default', $office))->assertRedirect();

        $buyer->refresh();
        $this->assertSame(['Metro Manila', 'Quezon City', 'Diliman', 'Katipunan Ave'], [$buyer->province, $buyer->municipality, $buyer->barangay, $buyer->street]);
        $this->assertEqualsWithDelta(14.65, (float) $buyer->delivery_lat, 1e-6);
        $this->assertModelMissing($office);

        $previous = BuyerAddress::sole();
        $this->assertSame(['Previous default', 'Cebu City', 'Lahug'], [$previous->label, $previous->municipality, $previous->barangay]);
        $this->assertEqualsWithDelta(10.33, (float) $previous->delivery_lat, 1e-6);
        $oldOrder->refresh();
        $this->assertSame([$previous->id, 'Cebu City'], [$oldOrder->buyer_address_id, $oldOrder->delivery_municipality], 'still going to Cebu');

        $this->actingAs($this->buyer())->post(route('buyer.addresses.default', $previous))->assertNotFound();
    }

    public function test_saved_addresses_have_their_own_pin_that_parcels_follow(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), [
            'recipient_name' => 'Ana', 'contact_no' => '09170000000', 'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug',
            'lat' => 10.33, 'lng' => 123.9,
        ])->assertSessionHasNoErrors();
        $office = BuyerAddress::sole();
        $this->assertEqualsWithDelta(10.33, (float) $office->delivery_lat, 1e-6);

        $onTheWay = $this->shipment($buyer->id, $seller->id, ['buyer_address_id' => $office->id, 'delivery_province' => 'Cebu', 'delivery_lat' => 10.33, 'delivery_lng' => 123.9]);
        $delivered = $this->shipment($buyer->id, $seller->id, ['buyer_address_id' => $office->id, 'delivery_province' => 'Cebu', 'delivery_lat' => 10.33, 'delivery_lng' => 123.9, 'status' => 'delivered']);

        $this->actingAs($buyer)->put(route('buyer.addresses.update', $office), [
            'recipient_name' => 'Ana', 'contact_no' => '09170000000', 'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug',
            'lat' => 10.34, 'lng' => 123.91,
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(10.34, (float) $onTheWay->fresh()->delivery_lat, 1e-6);
        $this->assertEqualsWithDelta(10.33, (float) $delivered->fresh()->delivery_lat, 1e-6, 'a delivered parcel keeps where it went');

        $this->actingAs($buyer)->post(route('buyer.addresses.store'), [
            'recipient_name' => 'Ana', 'contact_no' => '09170000000', 'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug', 'lat' => 40.7, 'lng' => -74,
        ])->assertSessionHasErrors(['lat', 'lng']);
    }

    public function test_followers_see_their_stores_and_hear_about_new_products(): void
    {
        Notification::fake();
        $seller = $this->seller(['business_name' => 'Lamp Shop']);
        $follower = $this->buyer();
        $stranger = $this->buyer();
        $this->actingAs($follower)->post(route('buyer.seller.follow', $seller));

        $this->actingAs($follower)->get(route('buyer.following'))->assertOk()->assertSee('Lamp Shop')->assertSee('Following');
        $this->actingAs($stranger)->get(route('buyer.following'))->assertOk()->assertSee("You don't follow any stores yet", false);

        $this->actingAs($seller)->post(route('seller.inventory.store'), [
            'name' => 'Desk Lamp', 'category' => 'Home & Living', 'price' => 900, 'discount' => 0, 'voucher_discount' => 0, 'stock' => 3, 'weight_kg' => 1,
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($follower, FollowedStoreNewProduct::class, fn ($n) => $n->product->name === 'Desk Lamp'
            && $n->toDatabase($follower)['url'] === route('buyer.product', Product::sole(), false));
        Notification::assertNotSentTo($stranger, FollowedStoreNewProduct::class);
    }

    public function test_sellers_read_their_ratings(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer(['first_name' => 'Ana']);
        $lamp = $this->product($seller);
        $this->order($buyer, $lamp, 'completed', ['rating' => 5, 'seller_rating' => 4, 'delivery_rating' => 2, 'feedback' => 'Bright and sturdy']);
        $this->order($buyer, $lamp, 'completed', ['rating' => 3, 'seller_rating' => 4, 'delivery_rating' => 4]);
        $this->order($buyer, $lamp, 'preparing');

        $this->actingAs($seller)->get(route('seller.reviews'))->assertOk()
            ->assertSeeInOrder(['Product rating', '★ 4.0', 'Seller service', '★ 4.0', 'Delivery', '★ 3.0'])
            ->assertSee('Bright and sturdy')->assertSee('Ana');
        $this->actingAs($this->seller())->get(route('seller.reviews'))->assertOk()->assertDontSee('Bright and sturdy');
    }

    public function test_options_can_have_their_own_price(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $shirt = $this->product($seller, ['name' => 'Shirt', 'price' => 300, 'discount' => 10, 'voucher_code' => 'TEN', 'voucher_discount' => 10, 'stock' => 9]);
        $small = ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'S', 'stock' => 3]);
        $xl = ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'XL', 'price' => 400, 'stock' => 3]);

        // ₱300 −10% = ₱270 for S; ₱400 −10% = ₱360 for XL.
        $this->actingAs($buyer)->get(route('buyer.product', $shirt))->assertOk()
            ->assertSee('₱270.00 – ₱360.00')->assertSee('data-price="₱360.00"', false);

        $this->cartLine($buyer, $shirt, 1, $small->id);
        $line = $this->cartLine($buyer, $shirt, 2, $xl->id);
        $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk()->assertSee('₱720.00');

        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id], 'voucher_code' => 'TEN'])->assertRedirect(route('buyer.orders'));
        // ₱360 −10% voucher = ₱324 each.
        $this->assertEquals(648, Order::sole()->amount);

        $this->actingAs($seller)->patch(route('seller.inventory.update', $shirt), [
            'name' => 'Shirt', 'category' => 'Fashion', 'price' => 300, 'discount' => 10, 'voucher_discount' => 10, 'stock' => 1, 'weight_kg' => 0.5,
            'variations_managed' => 1, 'variation_type' => 'Size',
            'variations' => [['id' => $small->id, 'value' => 'S', 'price' => '', 'stock' => 3], ['id' => $xl->id, 'value' => 'XL', 'price' => 450, 'stock' => 1]],
        ])->assertSessionHasNoErrors();
        $this->assertNull($small->fresh()->price);
        $this->assertEquals(450, $xl->fresh()->price);
        $this->assertSame(1, CartItem::count());
    }
}
