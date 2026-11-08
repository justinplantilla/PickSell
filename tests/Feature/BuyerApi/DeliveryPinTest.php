<?php

namespace Tests\Feature\BuyerApi;

use App\Models\Order;
use App\Models\OrderShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * The default address's door pin (website: account page "Delivery Pin") through
 * PATCH /api/mobile/buyer/delivery-location, and the pin and routing codes in delivery_address.
 * Moving pins of Pickrush parcels is covered in Pickrush\PicksellBridgeTest.
 */
class DeliveryPinTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_buyers_pin_their_door_like_on_the_website(): void
    {
        $buyer = $this->actingAsBuyer();
        $this->getJson('/api/mobile/buyer/profile')->assertOk()
            ->assertJsonPath('delivery_address.pin', null)
            ->assertJsonPath('delivery_address.full_address', '12, Gorordo Ave, Lahug, Cebu City, Cebu');

        $this->patchJson('/api/mobile/buyer/delivery-location', ['lat' => 10.3312, 'lng' => 123.8977])->assertOk()
            ->assertJsonPath('parcels_updated', 0)
            ->assertJsonPath('delivery_address.pin', ['lat' => 10.3312, 'lng' => 123.8977])
            ->assertJsonPath('message', 'Delivery pin saved. Pickrush riders will bring your orders to this spot.');
        $this->assertEqualsWithDelta(10.3312, (float) $buyer->fresh()->delivery_lat, 1e-6);

        // Every place the app reads the default address now carries the pin and the routing codes.
        $profile = $this->getJson('/api/mobile/buyer/profile')->assertOk()->json('delivery_address');
        $this->assertSame(['lat' => 10.3312, 'lng' => 123.8977], $profile['pin']);
        $this->assertArrayHasKey('municipality_code', $profile);
        $this->assertArrayHasKey('barangay_code', $profile);
        $this->getJson('/api/mobile/buyer/addresses')->assertJsonPath('default.pin', ['lat' => 10.3312, 'lng' => 123.8977]);
        $line = $this->cartLine($buyer, $this->product($this->seller()));
        $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$line->id]])
            ->assertJsonPath('delivery_address.pin', ['lat' => 10.3312, 'lng' => 123.8977])
            ->assertJsonPath('ship_to.pin', ['lat' => 10.3312, 'lng' => 123.8977]);
    }

    public function test_the_pin_must_be_in_the_philippines_with_the_website_messages(): void
    {
        $buyer = $this->actingAsBuyer();
        $this->patchJson('/api/mobile/buyer/delivery-location', [])->assertUnprocessable()
            ->assertJsonPath('errors.lat.0', 'Drop a pin on the map first.');
        $this->patchJson('/api/mobile/buyer/delivery-location', ['lat' => 40.7, 'lng' => -74.0])->assertUnprocessable()
            ->assertJsonPath('errors.lat.0', 'Pin a spot inside the Philippines.')
            ->assertJsonPath('errors.lng.0', 'Pin a spot inside the Philippines.');
        $this->assertNull($buyer->fresh()->delivery_lat);
    }

    public function test_the_pin_moves_with_orders_to_the_default_address_but_not_an_address_book_entry(): void
    {
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $home = $this->cartLine($buyer, $this->product($seller, ['name' => 'Lamp']));
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$home->id]])->assertCreated();
        $officeId = $this->postJson('/api/mobile/buyer/addresses', [
            'label' => 'Office', 'recipient_name' => 'Ana Cruz', 'contact_no' => '09175550101', 'lat' => 14.6538, 'lng' => 121.0685,
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Diliman', 'street' => 'Katipunan Ave', 'house_no' => '5',
        ])->json('addresses.0.id');
        $work = $this->cartLine($buyer, $this->product($this->seller(['business_name' => 'Mug Shop']), ['name' => 'Mug']));
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$work->id], 'address_id' => $officeId])->assertCreated();

        $this->patchJson('/api/mobile/buyer/delivery-location', ['lat' => 10.3312, 'lng' => 123.8977])->assertOk();

        $homeShipment = OrderShipment::whereNull('buyer_address_id')->sole();
        $officeShipment = OrderShipment::where('buyer_address_id', $officeId)->sole();
        $this->assertEqualsWithDelta(10.3312, (float) $homeShipment->delivery_lat, 1e-6, 'an order to the default address follows the new pin');
        $this->assertEqualsWithDelta(14.6538, (float) $officeShipment->delivery_lat, 1e-6, 'an order to the office keeps the office pin');

        // Each order says where it really ships, even after the profile or the book entry's place changes
        // (as on the website, the entry's pin still follows its open orders; it is kept here).
        $this->patchJson("/api/mobile/buyer/addresses/{$officeId}", [
            'label' => 'Office', 'recipient_name' => 'Ana Cruz', 'contact_no' => '09175550101', 'lat' => 14.6538, 'lng' => 121.0685,
            'province' => 'Metro Manila', 'municipality' => 'Makati', 'barangay' => 'Poblacion',
        ])->assertOk();
        $homeOrder = Order::where('product_name', 'Lamp')->sole();
        $officeOrder = Order::where('product_name', 'Mug')->sole();
        $this->getJson("/api/mobile/buyer/orders/{$homeOrder->id}")->assertOk()
            ->assertJsonPath('order.ship_to.full_address', '12, Gorordo Ave, Lahug, Cebu City, Cebu')
            ->assertJsonPath('order.ship_to.street', 'Gorordo Ave')
            ->assertJsonPath('order.ship_to.pin', ['lat' => 10.3312, 'lng' => 123.8977]);
        $this->getJson("/api/mobile/buyer/orders/{$officeOrder->id}")->assertOk()
            ->assertJsonPath('order.ship_to.recipient_name', 'Ana Cruz')
            ->assertJsonPath('order.ship_to.phone', '09175550101')
            ->assertJsonPath('order.ship_to.full_address', '5, Katipunan Ave, Diliman, Quezon City, Metro Manila')
            ->assertJsonPath('order.ship_to.municipality', 'Quezon City')
            ->assertJsonPath('order.ship_to.pin', ['lat' => 14.6538, 'lng' => 121.0685]);
    }
}
