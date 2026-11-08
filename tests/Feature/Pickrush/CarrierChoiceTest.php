<?php

namespace Tests\Feature\Pickrush;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Company;
use App\Pickrush\Services\Hq\HubCreator;
use App\Pickrush\Services\Shipping\RateCardCopier;
use App\Pickrush\Services\Shipping\RouteRateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * At checkout the buyer chooses the logistics company for each seller's parcel: Pickrush is
 * pre-selected, another approved company is offered when it has stations at both ends, and its
 * own rates price the parcel. The parcel is then booked with that company.
 */
class CarrierChoiceTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private User $seller;

    private User $buyer;

    private Company $bilis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->staff(StaffRole::HubManager, $this->station('041029000'));
        $this->seller = $this->user('seller', 'Batangas', 'City of Tanauan', ['business_name' => 'Tote House', 'municipality_code' => '041029000']);
        $this->buyer = $this->user('buyer', 'Batangas', 'City of Tanauan', ['municipality_code' => '041029000']);
        $this->user('admin', 'Batangas', 'City of Tanauan');

        // A second company in Tanauan, cheaper than Pickrush on the same-region route.
        $this->bilis = Company::create(['name' => 'Bilis Hatid Express', 'slug' => 'bilis', 'tracking_prefix' => 'BH', 'status' => 'approved', 'approved_at' => now()]);
        app(RateCardCopier::class)->copy($this->bilis);
        app(HubCreator::class)->province($this->bilis->id, '041000000');
        app(HubCreator::class)->station($this->bilis->id, '041029000');
        $level = DB::table('pr_shipping_route_levels')->where('company_id', $this->bilis->id)->where('origin_region', 'LUZON')->where('destination_region', 'LUZON')->value('level');
        app(RouteRateGenerator::class)->forCompany($this->bilis->id)->updateLevels([$level => ['fees' => ['0.50' => '50.00', '1.00' => '60.00', '2.00' => '70.00', '3.00' => '80.00', '5.00' => '90.00', '10.00' => '200.00'], 'per_kg' => '20']], today()->toDateString());
    }

    public function test_checkout_offers_both_carriers_with_pickrush_pre_selected(): void
    {
        $items = $this->cart($this->buyer, [[$this->product(), 1]]);

        $page = $this->actingAs($this->buyer)->get($this->url('/buyer/cart/checkout?'.http_build_query(['item_ids' => $items])))->assertOk();
        $page->assertSeeInOrder(['Ship with', 'Pickrush Logistics', 'recommended', 'Bilis Hatid Express', '₱60.00'])
            ->assertSee('<input type="hidden" name="carriers['.$this->seller->id.']" value="'.Company::defaultId().'">', false);

        $this->checkout($this->buyer, $items)->assertSessionHasNoErrors();
        $this->assertSame(Company::defaultId(), Order::sole()->shipment->logistics_company_id);
    }

    public function test_the_buyer_switches_to_another_carrier_and_its_price_and_parcel_follow(): void
    {
        $items = $this->cart($this->buyer, [[$this->product(), 1]]);
        $query = http_build_query(['item_ids' => $items, 'carrier' => [$this->seller->id => $this->bilis->id]]);
        $this->actingAs($this->buyer)->get($this->url("/buyer/cart/checkout?{$query}"))->assertOk()
            ->assertSee('· Bilis Hatid Express')->assertSee('<input type="hidden" name="carriers['.$this->seller->id.']" value="'.$this->bilis->id.'">', false);

        $this->checkout($this->buyer, $items, [$this->seller->id => $this->bilis->id])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame([$this->bilis->id, '60.00'], [$order->shipment->logistics_company_id, (string) $order->shipment->delivery_fee]);
        $this->assertSame('Bilis Hatid Express', $order->carrierName());

        $this->actingAs($this->seller)->patch($this->url("/seller/orders/{$order->id}/pack"));
        $this->actingAs($this->seller)->post($this->url("/seller/orders/{$order->id}/waybill"))->assertSessionHasNoErrors();
        $parcel = PicksellBridge::parcelFor($order);
        $this->assertSame($this->bilis->id, $parcel->company_id);
        $this->assertStringStartsWith('BH', $parcel->tracking_no);
        $this->assertSame($this->bilis->id, $parcel->originHub->company_id);
        $this->actingAs($this->seller)->get($this->url("/seller/orders/{$order->id}"))->assertSee('request a Bilis Hatid Express pickup')->assertDontSee('Pickrush pickup');
    }

    public function test_only_companies_that_serve_both_ends_are_offered(): void
    {
        $items = $this->cart($this->buyer, [[$this->product(), 1]]);
        // Bilis has no station in San Jose: a buyer there gets Pickrush only.
        $sanJose = $this->user('buyer', 'Batangas', 'San Jose', ['municipality_code' => '041005000']);
        $theirs = $this->cart($sanJose, [[$this->product(), 1]]);
        $this->actingAs($sanJose)->get($this->url('/buyer/cart/checkout?'.http_build_query(['item_ids' => $theirs])))->assertOk()
            ->assertDontSee('Bilis Hatid Express')->assertDontSee('Ship with');

        // A forged choice of a company that is not on offer falls back to Pickrush.
        $this->checkout($sanJose, $theirs, [$this->seller->id => $this->bilis->id])->assertSessionHasNoErrors();
        $this->assertSame(Company::defaultId(), Order::where('buyer_id', $sanJose->id)->sole()->shipment->logistics_company_id);

        // A suspended company is not offered either.
        $this->bilis->update(['status' => 'suspended']);
        $this->actingAs($this->buyer)->get($this->url('/buyer/cart/checkout?'.http_build_query(['item_ids' => $items])))->assertDontSee('Bilis Hatid Express');
    }

    public function test_the_app_lists_carriers_and_places_with_the_chosen_one(): void
    {
        $items = $this->cart($this->buyer, [[$this->product(), 1]]);
        Sanctum::actingAs($this->buyer);

        $quote = $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => $items])->assertOk();
        $quote->assertJsonPath('quote.groups.0.carrier', (string) Company::defaultId())
            ->assertJsonPath('quote.groups.0.carriers.0.name', 'Pickrush Logistics')->assertJsonPath('quote.groups.0.carriers.0.selected', true)
            ->assertJsonPath('quote.groups.0.carriers.1.name', 'Bilis Hatid Express')->assertJsonPath('quote.groups.0.carriers.1.delivery_fee', '60.00');

        $chosen = ['item_ids' => $items, 'carriers' => [$this->seller->id => $this->bilis->id]];
        $this->postJson('/api/mobile/buyer/checkout/quote', $chosen)->assertOk()
            ->assertJsonPath('quote.groups.0.carrier', (string) $this->bilis->id)->assertJsonPath('quote.totals.shipping_total', 60);
        $this->postJson('/api/mobile/buyer/checkout', $chosen)->assertCreated()->assertJsonPath('shipping_total', 60);
        $this->assertSame($this->bilis->id, Order::sole()->shipment->logistics_company_id);
    }

    private function url(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }

    private function user(string $role, string $province, string $municipality, array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Carrier{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@carrier.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => $province, 'municipality' => $municipality, 'barangay' => 'Altura Bata',
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }

    private function product(): Product
    {
        return Product::create([
            'seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion',
            'price' => 500, 'stock' => 10, 'status' => 'active', 'weight_kg' => 1,
        ]);
    }

    /** @param  array<int, array{0: Product, 1: int}>  $lines */
    private function cart(User $buyer, array $lines): array
    {
        $cart = Cart::firstOrCreate(['buyer_id' => $buyer->id]);

        return array_map(fn ($line) => CartItem::create(['cart_id' => $cart->id, 'product_id' => $line[0]->id, 'quantity' => $line[1]])->id, $lines);
    }

    private function checkout(User $buyer, array $itemIds, array $carriers = [])
    {
        return $this->actingAs($buyer)->post($this->url('/buyer/cart/checkout'), ['item_ids' => $itemIds, 'carriers' => $carriers]);
    }
}
