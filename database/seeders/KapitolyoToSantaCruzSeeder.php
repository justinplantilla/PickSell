<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Http\Controllers\Station\LocationController;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\Rider\RiderApplication;
use App\Pickrush\Services\Station\RiderOnboarding;
use App\Pickrush\Support\AccountContacts;
use App\Services\OrderPlacement;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Staffs Pickrush's route from Kapitolyo Threads (Kapitolyo, Pasig) to the Santa Cruz, Laguna test
 * buyer, then sends one order down it:
 *
 *   Pasig City Station → Metro Manila Eastern District Hub → Rizal Province Hub
 *     → Laguna Province Hub → Santa Cruz Station, Laguna
 *
 * Every hub on the route gets an address and map pin if it has none. Hubs that already have a
 * manager, trucks or riders (e.g. from MarikinaToSantaCruzSeeder) are left as they are; anything
 * missing is added: a hub manager, trucks and drivers, a pickup rider at Pasig and a delivery rider
 * at Santa Cruz (both through the real application and approval). Then a Cash on Delivery order of
 * an Oversized Cotton Tee that Kapitolyo Threads has prepared and handed over, so it waits for pickup.
 *
 *   php artisan db:seed --class=SantaCruzBuyerSeeder
 *   php artisan db:seed --class=KapitolyoToSantaCruzSeeder
 *
 * Managers sign in to the logistics portal with Manager@1234, riders to the rider app with
 * Rider@1234 (emails below). Safe to run again: whatever already exists is left as it is.
 */
class KapitolyoToSantaCruzSeeder extends Seeder
{
    private const SELLER = 'fashion.pasig@picksell.ph';

    private const BUYER = 'santacruz.buyer@picksell.ph';

    private const PRODUCT = 'Oversized Cotton Tee';

    private const MANAGER_PASSWORD = 'Manager@1234';

    private const RIDER_PASSWORD = 'Rider@1234';

    /** Every Pickrush hub on the route and the manager it gets if it has none: code => [name, email, mobile]. */
    private const MANAGERS = [
        'S-137403000' => ['Pasig Station Manager', 'pasig.manager@pickrush.test', '09171374030'],
        'P-137400000' => ['Eastern District Hub Manager', 'ncr-eastern.manager@pickrush.test', '09171374000'],
        'P-043400000' => ['Laguna Hub Manager', 'laguna.manager@pickrush.test', '09170434000'],
        'P-045800000' => ['Rizal Hub Manager', 'rizal.manager@pickrush.test', '09170458000'],
        'S-043426000' => ['Santa Cruz Station Manager', 'santacruz.manager@pickrush.test', '09170434260'],
    ];

    /** Every hub on the route: code => [street or building, barangay code, city code, lat, lng]. */
    private const PINS = [
        'S-137403000' => ['Shaw Blvd, near Kapitolyo', '137403009', '137403000', 14.5712, 121.0601],
        'P-137400000' => ['Ortigas Ave Extension, corner C-5', '137403017', '137403000', 14.5893, 121.0903],
        'P-045800000' => ['Sumulong Highway, near Antipolo Cathedral', '045802007', '045802000', 14.5866, 121.1760],
        'P-043400000' => ['Pedro Guevara Ave, near Laguna Provincial Capitol', '043426002', '043426000', 14.2830, 121.4155],
        'S-043426000' => ['A. Mabini St, near Santa Cruz Public Market', '043426014', '043426000', 14.2789, 121.4173],
    ];

    public function run(): void
    {
        $seller = User::where('email', self::SELLER)->first()
            ?? throw new RuntimeException(self::SELLER.' is missing. Run php artisan db:seed --class=CategorySellersSeeder first.');
        $buyer = User::where('email', self::BUYER)->first()
            ?? throw new RuntimeException(self::BUYER.' is missing. Run php artisan db:seed --class=SantaCruzBuyerSeeder first.');

        $company = Company::find(Company::defaultId());
        $hubs = Hub::ofCompany($company->id)->whereIn('code', array_keys(self::MANAGERS))->get()->keyBy('code');
        if ($hubs->count() !== count(self::MANAGERS)) {
            throw new RuntimeException('Pickrush is missing a hub on the route. Run php artisan pickrush:build-hubs --import first.');
        }

        DB::transaction(function () use ($hubs, $company) {
            foreach (Hub::ofCompany($company->id)->whereIn('code', array_keys(self::PINS))->get() as $hub) {
                $this->pin($hub, ...self::PINS[$hub->code]);
            }
            foreach (self::MANAGERS as $code => [$name, $email, $mobile]) {
                $this->manager($company, $hubs[$code], $name, $email, $mobile);
                $this->fleet($hubs[$code], $company->name);
            }
        });

        $this->rider($hubs['S-137403000'], [
            'first_name' => 'Carlo', 'last_name' => 'Ramos', 'birth_date' => '1996-04-11',
            'mobile' => '9171374031', 'email' => 'pasig.rider@pickrush.test',
            'province_code' => '137400000', 'province_name' => 'Metro Manila',
            'city_code' => '137403000', 'city_name' => 'Pasig',
            'barangay_code' => '137403009', 'barangay_name' => 'Kapitolyo',
            'street' => 'East Capitol Dr', 'plate_number' => 'NCR 1375', 'license_no' => 'N13-74-000031',
        ]);
        $this->rider($hubs['S-043426000'], [
            'first_name' => 'Leo', 'last_name' => 'Valdez', 'birth_date' => '1994-12-19',
            'mobile' => '9170434261', 'email' => 'santacruz.rider@pickrush.test',
            'province_code' => '043400000', 'province_name' => 'Laguna',
            'city_code' => '043426000', 'city_name' => 'Santa Cruz',
            'barangay_code' => '043426011', 'barangay_name' => 'Pagsawitan',
            'street' => 'National Highway', 'plate_number' => 'LAG 4261', 'license_no' => 'D04-26-000261',
        ]);

        $this->order($buyer, $seller);
    }

    /** The hub's address and map pin, as its manager sets them under Location; a pin already dropped is kept. */
    private function pin(Hub $hub, string $line, string $barangayCode, string $cityCode, float $lat, float $lng): void
    {
        if ($hub->lat !== null && $hub->lng !== null) {
            return;
        }

        $hub->update([
            'address_city_code' => $cityCode,
            'address_barangay_code' => $barangayCode,
            'address_line' => $line,
            'address' => LocationController::compose($hub, $line, $barangayCode, $cityCode),
            'lat' => $lat,
            'lng' => $lng,
        ]);
        $this->command?->info("{$hub->name}: pinned at {$hub->address}");
    }

    private function manager(Company $company, Hub $hub, string $name, string $email, string $mobile): void
    {
        if (Staff::where('hub_id', $hub->id)->where('role', StaffRole::HubManager)->where('is_active', true)->exists()) {
            return;
        }
        if (AccountContacts::emailTaken($email)) {
            $this->command?->warn("{$email} already exists; left as it is.");

            return;
        }

        Staff::create([
            'company_id' => $company->id,
            'full_name' => $name,
            'email' => $email,
            'mobile_no' => AccountContacts::mobileTaken($mobile) ? null : $mobile,
            'password' => self::MANAGER_PASSWORD,
            'role' => StaffRole::HubManager,
            'hub_id' => $hub->id,
            'is_active' => true,
            'is_sample' => false,
            'email_verified_at' => now(),
        ]);
        $this->command?->info("{$hub->name}: {$email} / ".self::MANAGER_PASSWORD);
    }

    private function fleet(Hub $hub, string $owner): void
    {
        $hasFleet = Vehicle::withoutGlobalScopes()->where('hub_id', $hub->id)->exists()
            || Driver::withoutGlobalScopes()->where('hub_id', $hub->id)->exists();
        if ($hasFleet) {
            return;
        }

        app(FleetSeeder::class)->seedHub($hub, $owner, $hub->id);
        $this->command?->info("{$hub->name}: ".Vehicle::limitFor($hub).' truck(s) with drivers');
    }

    /** Applies in the rider app with placeholder documents, then is approved by the station's manager. */
    private function rider(Hub $station, array $data): void
    {
        if (Rider::withoutGlobalScopes()->where('email', $data['email'])->exists()) {
            $this->command?->warn("{$data['email']} already exists; left as it is.");

            return;
        }
        $manager = Staff::where('hub_id', $station->id)->where('role', StaffRole::HubManager)->where('is_active', true)->firstOrFail();

        $rider = app(RiderApplication::class)->apply($data + [
            'hub_id' => $station->id,
            'password' => self::RIDER_PASSWORD,
            'vehicle_type' => 'motorcycle',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'orcr_expiry' => now()->addYear()->toDateString(),
        ], [
            'license_front' => UploadedFile::fake()->create('license-front.jpg', 40, 'image/jpeg'),
            'license_back' => UploadedFile::fake()->create('license-back.jpg', 40, 'image/jpeg'),
            'orcr' => [UploadedFile::fake()->create('or-cr.jpg', 40, 'image/jpeg')],
        ]);

        auth('pickrush')->setUser($manager);
        app(RiderOnboarding::class)->approve($rider, $manager);

        $this->command?->info("{$station->name}: rider {$data['email']} / ".self::RIDER_PASSWORD);
    }

    /** Checked out like the website does, then prepared, waybilled and handed over by the seller. */
    private function order(User $buyer, User $seller): void
    {
        if (Order::where('buyer_id', $buyer->id)->where('seller_id', $seller->id)->exists()) {
            $this->command?->warn("{$buyer->email} already has an order from {$seller->business_name}; no new order.");

            return;
        }
        $product = Product::where('seller_id', $seller->id)->where('name', self::PRODUCT)->firstOrFail();

        $cart = Cart::firstOrCreate(['buyer_id' => $buyer->id]);
        $line = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $order = app(OrderPlacement::class)->place($buyer, [$line->id], null)->first();

        $lifecycle = app(OrderLifecycleService::class);
        $pickrush = app(PicksellBridge::class);
        $parcel = DB::transaction(function () use ($order, $seller, $lifecycle, $pickrush) {
            $lifecycle->transition($order, 'preparing', $seller->id, 'seller', null, [
                'packed_at' => now(),
                'tracking_status' => 'Seller is preparing the order',
            ]);
            $parcel = $pickrush->book($order);
            $order->update(['waybill_number' => $parcel->tracking_no]);
            $pickrush->requestPickup($order);
            $lifecycle->transition($order->fresh(), 'ready_for_pickup', $seller->id, 'seller', null, [
                'waybill_number' => $parcel->tracking_no,
                'handed_over_at' => now(),
                'tracking_status' => 'Pickup requested from seller',
            ]);

            return $parcel;
        });

        $this->command?->info("Order {$order->order_number} ({$product->name}) handed over: waybill {$parcel->tracking_no}, "
            ."COD ₱{$parcel->fresh()->cod_amount}, waiting for pickup at Pasig City Station.");
    }
}
