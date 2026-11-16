<?php

namespace Tests\Feature\Pickrush;

use App\Models\Order;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\HubLink;
use App\Pickrush\Services\HubNetworkBuilder;
use App\Pickrush\Services\Station\DispatchService;
use Database\Seeders\CebuDispatchOrderSeeder;
use Database\Seeders\ShippingRatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class CebuDispatchOrderSeederTest extends TestCase
{
    use BuildsMarketplace, BuildsNetwork, RefreshDatabase;

    public function test_order_waits_at_cebu_dispatch_and_reruns_leave_it_untouched(): void
    {
        Notification::fake();
        $this->buildNetwork();
        DB::table('geo_regions')->insert(['code' => '070000000', 'name' => 'Region VII', 'region_name' => 'Central Visayas']);
        DB::table('geo_provinces')->insert(['code' => '072200000', 'region_code' => '070000000', 'name' => 'Cebu', 'kind' => 'province']);
        DB::table('geo_municipalities')->insert(['code' => '072217000', 'province_code' => '072200000', 'region_code' => '070000000', 'name' => 'Cebu City', 'is_city' => 1, 'is_capital' => 1]);
        DB::table('geo_barangays')->insert(['code' => '072217027', 'municipality_code' => '072217000', 'name' => 'Guadalupe']);
        app(HubNetworkBuilder::class)->build();
        $cebu = $this->station('072217000');
        $origin = $this->station('041029000');
        [$a, $b] = HubLink::pair($origin->parent_hub_id, $cebu->parent_hub_id);
        HubLink::create(['hub_a_id' => $a, 'hub_b_id' => $b, 'mode' => 'sea', 'travel_hours' => 12, 'is_active' => true]);
        $this->seed(ShippingRatesSeeder::class);
        $buyer = $this->buyer(['email' => 'cebu.buyer@picksell.ph', 'first_name' => 'Andrea', 'last_name' => 'Villanueva', 'barangay' => 'Guadalupe', 'delivery_lat' => 10.3221, 'delivery_lng' => 123.8863]);
        $seller = $this->seller(['email' => 'homeliving.marikina@picksell.ph', 'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata']);
        $product = $this->product($seller, ['name' => 'Stackable Food Containers (Set of 5)', 'stock' => 10]);
        $existingCartLine = $this->cartLine($buyer, $product, 2);

        $this->seed(CebuDispatchOrderSeeder::class);
        $order = Order::where('order_number', CebuDispatchOrderSeeder::ORDER_NUMBER)->firstOrFail();
        $parcel = PicksellBridge::parcelFor($order);
        $this->assertSame($buyer->id, $order->buyer_id);
        $this->assertSame(ParcelStatus::ArrivedAtStation, $parcel->current_status);
        $this->assertSame($cebu->id, $parcel->current_hub_id);
        $this->assertSame('delivery', DispatchService::taskFor($parcel, $cebu));
        $this->assertFalse($parcel->openAssignment()->exists());
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(2, $existingCartLine->fresh()->quantity);
        $events = $parcel->events()->count();
        $this->seed(CebuDispatchOrderSeeder::class);
        $this->assertSame(1, Order::where('order_number', CebuDispatchOrderSeeder::ORDER_NUMBER)->count());
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame($events, $parcel->events()->count());
    }
}
