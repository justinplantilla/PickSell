<?php

namespace Tests\Feature\Pickrush;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Support\Codes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelsTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    /** Reads Code 128 module widths back into text, checking start, checksum and stop. */
    private function decode128(array $widths): string
    {
        $patterns = array_flip((new \ReflectionClassConstant(Codes::class, 'PATTERNS'))->getValue());
        $this->assertSame('2331112', implode('', array_slice($widths, -7)), 'ends with the stop pattern');
        $symbols = array_map(fn ($chunk) => $patterns[implode('', $chunk)], array_chunk(array_slice($widths, 0, -7), 6));
        $this->assertSame(104, array_shift($symbols), 'starts with Start B');
        $check = array_pop($symbols);
        $sum = 104;
        foreach ($symbols as $i => $value) {
            $sum += ($i + 1) * $value;
        }
        $this->assertSame($sum % 103, $check, 'checksum');

        return implode('', array_map(fn ($v) => chr($v + 32), $symbols));
    }

    public function test_code128_patterns_follow_the_standard_and_round_trip(): void
    {
        $patterns = (new \ReflectionClassConstant(Codes::class, 'PATTERNS'))->getValue();
        $this->assertCount(107, $patterns);
        $this->assertCount(107, array_unique($patterns), 'every symbol is distinct');
        foreach (array_slice($patterns, 0, 106) as $value => $pattern) {
            $this->assertSame(11, array_sum(str_split($pattern)), "symbol {$value} is 11 modules wide");
            $this->assertSame(0, array_sum(array_filter(str_split($pattern), fn ($w, $i) => $i % 2 === 0, ARRAY_FILTER_USE_BOTH)) % 2, "symbol {$value} has even bar parity");
        }
        $this->assertSame(['211412', '211214', '211232'], [$patterns[103], $patterns[104], $patterns[105]], 'start A/B/C');

        foreach (['PR2610200483921K', 'MF261021AB12CD', 'Hello, Pickrush!'] as $text) {
            $this->assertSame($text, $this->decode128(Codes::code128($text)));
        }
        $this->assertGreaterThanOrEqual(25, count(Codes::qr('PR2610200483921K')), 'QR version 2 or higher with a quiet zone');
    }

    public function test_hub_staff_print_labels_and_manifests_for_their_own_parcels(): void
    {
        $this->buildNetwork();
        $tanauan = $this->station('041029000');
        $manager = $this->staff(StaffRole::HubManager, $tanauan);
        $parcel = app(ParcelRegistrar::class)->register(['shipper_name' => 'Tote House', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Ana Reyes', 'delivery_municipality_code' => '043405000', 'cod_amount' => 750]);

        $this->actingAs($manager, 'pickrush')->get($this->pickrushUrl("/parcels/{$parcel->tracking_no}/label"))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->staff(StaffRole::HubManager, $this->station('041005000')), 'pickrush')
            ->get($this->pickrushUrl("/parcels/{$parcel->tracking_no}/label"))->assertNotFound();

        $this->actingAs($manager, 'pickrush');
        app(ParcelLedger::class)->record($parcel, ParcelStatus::AtOriginHub, $tanauan);
        $trip = app(ManifestService::class)->create($tanauan, $tanauan->parent, $manager);
        app(ManifestService::class)->add($trip, $parcel->tracking_no);
        $this->actingAs($manager, 'pickrush')->get($this->pickrushUrl("/station/trips/{$trip->manifest_no}/print"))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $view = view('pickrush.labels.parcel', ['parcel' => $parcel->load('originHub', 'destHub'), 'orderNumber' => null])->render();
        $this->assertStringContainsString('aria-label="Barcode '.$parcel->tracking_no.'"', $view);
        $this->assertStringContainsString('aria-label="QR code '.$parcel->tracking_no.'"', $view);
        $this->assertStringContainsString('CALAMBA CITY', $view, 'big destination bin');
        $this->assertStringContainsString('₱750.00', $view);
    }

    public function test_the_seller_waybill_is_the_pickrush_label_once_handed_over(): void
    {
        $this->buildNetwork();
        $user = fn (string $role, string $municipality) => User::create([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => 'Label', 'sex' => 'Female',
            'email' => "{$role}@label.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Batangas', 'municipality' => $municipality, 'barangay' => 'Altura Bata', 'business_name' => $role === 'seller' ? 'Tote House' : null,
        ]);
        $seller = $user('seller', 'City of Tanauan');
        $buyer = $user('buyer', 'San Jose');
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Tote', 'category' => 'Fashion', 'price' => 500, 'stock' => 3, 'status' => 'active']);
        $order = Order::create(['order_number' => 'ORD-LABEL', 'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'product_name' => 'Tote', 'quantity' => 1, 'amount' => 500, 'commission' => 50, 'status' => 'preparing']);

        $this->actingAs($seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover")->assertRedirect();
        $this->actingAs($seller)->get(config('app.url')."/seller/orders/{$order->id}/waybill")
            ->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=pickrush-label-'.$order->fresh()->waybill_number.'.pdf');
    }

    public function test_scanning_a_trip_barcode_at_receiving_opens_the_trip_check(): void
    {
        $this->buildNetwork();
        $tanauan = $this->station('041029000');
        $stationStaff = $this->staff(StaffRole::Dispatcher, $tanauan);
        $sorter = $this->staff(StaffRole::Sorter, $tanauan->parent);
        $parcel = app(ParcelRegistrar::class)->register(['shipper_name' => 'Tote House', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Ana Reyes', 'delivery_municipality_code' => '043405000', 'cod_amount' => 750]);
        $this->actingAs($stationStaff, 'pickrush');
        app(ParcelLedger::class)->record($parcel, ParcelStatus::AtOriginHub, $tanauan);
        $trip = app(ManifestService::class)->create($tanauan, $tanauan->parent, $stationStaff);
        app(ManifestService::class)->add($trip, $parcel->tracking_no);
        app(ManifestService::class)->dispatch($trip, 'ABC 1', 'Jose', $stationStaff);

        $this->actingAs($sorter, 'pickrush')->post($this->pickrushUrl('/province/inbound'), ['tracking_no' => strtolower($trip->manifest_no)])
            ->assertRedirect(route('pickrush.province.trips.show', $trip));
        $this->assertSame(ParcelStatus::InTransitToProvince, $parcel->fresh()->current_status, 'opening the trip receives nothing by itself');
        $this->assertSame('dispatched', Manifest::find($trip->id)->status);
    }
}
