<?php

namespace Tests\Feature;

use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class BuyerOrdersAndCheckoutTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_an_order_card_leads_to_the_store_the_chat_and_the_product(): void
    {
        $seller = $this->seller(['business_name' => 'Lamp Shop']);
        $buyer = $this->buyer();
        $lamp = $this->product($seller);
        $order = $this->order($buyer, $lamp, 'preparing');

        $page = $this->actingAs($buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee('href="'.route('buyer.seller', $seller).'" class="orders-store-name"', false)
            ->assertSee('href="/buyer/chat?user='.$seller->id.'"', false)
            ->assertSee('href="'.route('buyer.product', $lamp).'" class="orders-thumb-link"', false);

        // The order number moved out of the card header into Details.
        $html = $page->getContent();
        $this->assertGreaterThan(strpos($html, 'id="order-details-'.$order->id.'"'), strpos($html, $order->order_number));
    }

    public function test_buy_again_puts_a_completed_order_back_in_the_cart(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $shirt = $this->product($seller, ['stock' => 5]);
        $medium = ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'M', 'stock' => 1]);
        ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'L', 'stock' => 4]);
        $order = $this->order($buyer, $shirt, 'completed', ['variation_id' => $medium->id, 'variation_label' => 'Size: M']);
        $pending = $this->order($buyer, $shirt, 'preparing');

        $this->actingAs($buyer)->get(route('buyer.orders'))->assertSee(route('buyer.orders.buy-again', $order), false)
            ->assertDontSee(route('buyer.orders.buy-again', $pending), false);

        $this->actingAs($buyer)->post(route('buyer.orders.buy-again', $order))->assertRedirect(route('buyer.cart'));
        // Same option, capped at what the option has left.
        $this->assertSame([$medium->id, 1], [CartItem::sole()->variation_id, CartItem::sole()->quantity]);

        $this->actingAs($buyer)->post(route('buyer.orders.buy-again', $pending))->assertNotFound();
        $this->actingAs($this->buyer())->post(route('buyer.orders.buy-again', $order))->assertForbidden();

        // The seller removed that option: the buyer picks another on the product page.
        $medium->delete();
        $this->actingAs($buyer)->post(route('buyer.orders.buy-again', $order))
            ->assertRedirect(route('buyer.product', $shirt))->assertSessionHasErrors('variation_id');
    }

    public function test_buyers_follow_stores_and_see_their_standing(): void
    {
        $seller = $this->seller(['business_name' => 'Lamp Shop']);
        $buyer = $this->buyer();
        $lamp = $this->product($seller);

        $this->actingAs($buyer)->get(route('buyer.seller', $seller))->assertOk()
            ->assertSee('+ Follow')->assertSee('0 followers')->assertSee('Joined '.$seller->created_at->format('M Y'))
            ->assertSee('href="/buyer/chat?user='.$seller->id.'"', false);

        $this->actingAs($buyer)->post(route('buyer.seller.follow', $seller))->assertRedirect();
        $this->actingAs($buyer)->get(route('buyer.product', $lamp))->assertOk()->assertSee('Following')->assertSee('1 follower');

        $this->actingAs($buyer)->postJson(route('buyer.seller.follow', $seller))->assertOk()->assertJson(['following' => false, 'followers' => 0]);
        $this->actingAs($buyer)->post(route('buyer.seller.follow', $buyer))->assertNotFound();
    }

    public function test_checkout_ships_to_a_chosen_address_with_a_note_to_the_seller(): void
    {
        $seller = $this->seller(['business_name' => 'Lamp Shop']);
        $buyer = $this->buyer();
        $line = $this->cartLine($buyer, $this->product($seller));

        $this->actingAs($buyer)->post(route('buyer.addresses.store'), [
            'item_ids' => [$line->id], 'label' => 'Office', 'recipient_name' => 'Ana Cruz', 'contact_no' => '+63 917 555 0101',
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Diliman', 'street' => 'Katipunan Ave', 'house_no' => '5',
        ])->assertRedirect();
        $office = BuyerAddress::sole();
        $this->assertSame('09175550101', $office->contact_no);

        $default = $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id]]))->assertOk()
            ->assertSee('Lahug, Cebu City, Cebu')->assertSee('Office');
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id], 'address_id' => $office->id]))->assertOk()
            ->assertSee('Ana Cruz')->assertSee('5, Katipunan Ave, Diliman, Quezon City, Metro Manila')
            ->assertSee('name="address_id" value="'.$office->id.'"', false);

        $this->actingAs($buyer)->post(route('buyer.checkout'), [
            'item_ids' => [$line->id], 'address_id' => $office->id, 'notes' => [$seller->id => '  Gift, please wrap  '],
        ])->assertRedirect(route('buyer.orders'));

        $shipment = OrderShipment::sole();
        $this->assertSame([$office->id, 'Ana Cruz', '09175550101', 'Quezon City', 'Gift, please wrap'],
            [$shipment->buyer_address_id, $shipment->recipient_name, $shipment->recipient_phone, $shipment->delivery_municipality, $shipment->buyer_note]);
        $this->assertNotSame('Visayas', $shipment->destination_region, 'priced to Metro Manila, not the Cebu profile address');

        // Editing the address book later leaves the booked order alone.
        $office->update(['municipality' => 'Makati']);
        $order = Order::sole();
        $this->assertSame('5, Katipunan Ave, Diliman, Quezon City, Metro Manila', $order->fresh()->deliverTo()['address']);
        $this->actingAs($seller)->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('Ana Cruz')->assertSee('Gift, please wrap')->assertSee('Quezon City');
    }

    public function test_addresses_are_private_to_their_buyer(): void
    {
        $owner = $this->buyer();
        $address = BuyerAddress::create(['user_id' => $owner->id, 'recipient_name' => 'Ana', 'contact_no' => '09170000000',
            'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug']);
        $other = $this->buyer();

        $this->actingAs($other)->delete(route('buyer.addresses.destroy', $address))->assertNotFound();
        $this->actingAs($other)->put(route('buyer.addresses.update', $address), ['recipient_name' => 'X'])->assertNotFound();
        $this->actingAs($other)->get(route('buyer.checkout.start', ['item_ids' => [$this->cartLine($other, $this->product($this->seller()))->id], 'address_id' => $address->id]))
            ->assertOk()->assertDontSee('Ana');
        $this->actingAs($other)->post(route('buyer.checkout'), ['item_ids' => [CartItem::sole()->id], 'address_id' => $address->id])->assertNotFound();
        $this->assertModelExists($address);
    }

    public function test_an_incomplete_address_cannot_be_ordered_to(): void
    {
        $buyer = $this->buyer(['barangay' => '']);
        $line = $this->cartLine($buyer, $this->product($this->seller()));

        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id]]))->assertOk()
            ->assertSee('Complete the delivery address first.');
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id]])->assertSessionHasErrors('address');
        $this->assertSame(0, Order::count());
    }
}
