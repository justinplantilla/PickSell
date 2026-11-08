<?php

namespace Tests\Feature\BuyerApi;

use App\Models\BuyerAddress;
use App\Models\OrderShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The website's address book (BuyerAddressBook) through /api/mobile/buyer/addresses, and choosing one at checkout. */
class AddressBookTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function office(array $extra = []): array
    {
        return $extra + [
            'label' => 'Office', 'recipient_name' => 'Ana Cruz', 'contact_no' => '+63 917 555 0101',
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Diliman', 'street' => 'Katipunan Ave', 'house_no' => '5',
        ];
    }

    public function test_buyers_add_edit_remove_and_list_addresses_like_the_website(): void
    {
        $this->actingAsBuyer();
        $this->getJson('/api/mobile/buyer/addresses')->assertOk()
            ->assertJsonPath('addresses', [])->assertJsonPath('max', 10)
            ->assertJsonPath('default.full_address', '12, Gorordo Ave, Lahug, Cebu City, Cebu');

        $book = $this->postJson('/api/mobile/buyer/addresses', $this->office(['lat' => 14.6538, 'lng' => 121.0685]))->assertCreated()->json();
        $entry = $book['addresses'][0];
        $this->assertSame([
            'id' => BuyerAddress::sole()->id, 'label' => 'Office', 'recipient_name' => 'Ana Cruz', 'phone' => '09175550101',
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Diliman', 'street' => 'Katipunan Ave', 'house_no' => '5',
            'full_address' => '5, Katipunan Ave, Diliman, Quezon City, Metro Manila', 'complete' => true, 'pin' => ['lat' => 14.6538, 'lng' => 121.0685],
        ], $entry);

        $this->patchJson("/api/mobile/buyer/addresses/{$entry['id']}", $this->office(['label' => 'Work', 'house_no' => '7']))->assertOk()
            ->assertJsonPath('addresses.0.label', 'Work')->assertJsonPath('addresses.0.pin', null)
            ->assertJsonPath('addresses.0.full_address', '7, Katipunan Ave, Diliman, Quezon City, Metro Manila');

        // The website's validation and messages.
        $this->postJson('/api/mobile/buyer/addresses', $this->office(['contact_no' => '12345', 'barangay' => '']))->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_no', 'barangay'])
            ->assertJsonPath('errors.contact_no.0', 'Enter an 11-digit mobile number starting with 09, like 09171234567.');
        $this->postJson('/api/mobile/buyer/addresses', $this->office(['lat' => 40, 'lng' => 121]))->assertUnprocessable()
            ->assertJsonPath('errors.lat.0', 'Pin a spot inside the Philippines.');

        $this->deleteJson("/api/mobile/buyer/addresses/{$entry['id']}")->assertOk()->assertJsonPath('addresses', []);
        $this->assertSame(0, BuyerAddress::count());
    }

    public function test_up_to_ten_addresses_and_only_your_own(): void
    {
        $buyer = $this->actingAsBuyer();
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/mobile/buyer/addresses', $this->office(['label' => "Place {$i}"]))->assertCreated();
        }
        $this->postJson('/api/mobile/buyer/addresses', $this->office())->assertUnprocessable()
            ->assertJsonPath('errors.address.0', 'You can save up to 10 addresses.');

        $theirs = BuyerAddress::create(['user_id' => $this->buyer()->id, 'recipient_name' => 'Ben', 'contact_no' => '09170000000',
            'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug']);
        $this->patchJson("/api/mobile/buyer/addresses/{$theirs->id}", $this->office())->assertNotFound();
        $this->deleteJson("/api/mobile/buyer/addresses/{$theirs->id}")->assertNotFound();
        $this->postJson("/api/mobile/buyer/addresses/{$theirs->id}/default")->assertNotFound();
        $this->assertModelExists($theirs);
        $this->assertCount(10, $buyer->addresses);
    }

    public function test_making_an_address_the_default_keeps_the_old_default_in_the_book(): void
    {
        $buyer = $this->actingAsBuyer();
        $id = $this->postJson('/api/mobile/buyer/addresses', $this->office())->json('addresses.0.id');

        $book = $this->postJson("/api/mobile/buyer/addresses/{$id}/default")->assertOk()->json();

        $this->assertSame('5, Katipunan Ave, Diliman, Quezon City, Metro Manila', $book['default']['full_address']);
        $this->assertSame(['Previous default'], array_column($book['addresses'], 'label'));
        $this->assertSame('12, Gorordo Ave, Lahug, Cebu City, Cebu', $book['addresses'][0]['full_address']);
        $this->assertSame('Quezon City', $buyer->fresh()->municipality);
        $this->assertNull(BuyerAddress::find($id));
    }

    public function test_checkout_quotes_and_ships_to_the_chosen_address(): void
    {
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $line = $this->cartLine($buyer, $this->product($seller));
        $id = $this->postJson('/api/mobile/buyer/addresses', $this->office())->json('addresses.0.id');

        // Without address_id: the profile address, exactly as before (Cebu to Cebu, ₱95).
        $default = $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$line->id]])->assertOk()
            ->assertJsonPath('delivery_address.full_address', '12, Gorordo Ave, Lahug, Cebu City, Cebu')
            ->assertJsonPath('ship_to.address_id', null)->assertJsonPath('ship_to.label', 'Default')
            ->json('quote.totals.shipping_total');

        $chosen = $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$line->id], 'address_id' => $id])->assertOk()
            ->assertJsonPath('delivery_address.full_address', '12, Gorordo Ave, Lahug, Cebu City, Cebu')
            ->assertJsonPath('ship_to', [
                'address_id' => $id, 'label' => 'Office', 'recipient_name' => 'Ana Cruz', 'phone' => '09175550101',
                'full_address' => '5, Katipunan Ave, Diliman, Quezon City, Metro Manila', 'complete' => true, 'pin' => null,
            ])->json('quote.totals.shipping_total');
        $this->assertGreaterThan($default, $chosen, 'Cebu seller to Metro Manila costs more than within Cebu');

        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$line->id], 'address_id' => $id, 'payment_method' => 'cod'])
            ->assertCreated()->assertJsonPath('shipping_total', $chosen);
        $shipment = OrderShipment::sole();
        $this->assertSame([$id, 'Ana Cruz', '09175550101', 'Quezon City'],
            [$shipment->buyer_address_id, $shipment->recipient_name, $shipment->recipient_phone, $shipment->delivery_municipality]);
    }

    public function test_checkout_refuses_another_buyers_address(): void
    {
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $line = $this->cartLine($buyer, $this->product($seller));
        $theirs = BuyerAddress::create(['user_id' => $this->buyer()->id, 'recipient_name' => 'Ben', 'contact_no' => '09170000000',
            'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug']);

        $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$line->id], 'address_id' => $theirs->id])
            ->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->postJson('/api/mobile/buyer/checkout', ['item_ids' => [$line->id], 'address_id' => $theirs->id])
            ->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->assertSame(0, OrderShipment::count());
    }
}
