<?php

namespace Database\Seeders;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Http\Controllers\Station\LocationController;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubLink;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\Hq\HubCreator;
use App\Pickrush\Services\Rider\RiderApplication;
use App\Pickrush\Services\Station\RiderOnboarding;
use App\Pickrush\Support\AccountContacts;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Opens and staffs MobLoop's route from Kapitolyo Threads (Kapitolyo, Pasig) to Santa Cruz, Laguna,
 * so buyers there can pick MobLoop instead of Pickrush at checkout:
 *
 *   Pasig City Station → Metro Manila Eastern District Hub → Rizal Province Hub
 *     → Laguna Province Hub → Santa Cruz Station, Laguna
 *
 * Opens any hub MobLoop is missing, links its province hubs the way Pickrush's are linked, pins
 * every hub, and gives each a hub manager, trucks and drivers. Adds a pickup rider at Pasig and a
 * delivery rider at Santa Cruz through the real application and approval. No order is placed.
 *
 *   php artisan db:seed --class=MobLoopManagersSeeder
 *   php artisan db:seed --class=MobLoopKapitolyoToSantaCruzSeeder
 *
 * Managers sign in to the logistics portal with Manager@1234, riders to the rider app with
 * Rider@1234 (emails below). Safe to run again: whatever already exists is left as it is.
 */
class MobLoopKapitolyoToSantaCruzSeeder extends Seeder
{
    private const COMPANY = 'MobLoop';

    private const MANAGER_PASSWORD = 'Manager@1234';

    private const RIDER_PASSWORD = 'Rider@1234';

    /** Province hubs on the route, in order: PSGC province code. */
    private const PROVINCES = ['137400000', '045800000', '043400000'];

    /** Stations on the route: PSGC city code. */
    private const STATIONS = ['137403000', '043426000'];

    /** Every hub on the route and the manager it gets if it has none: code => [name, email, mobile]. */
    private const MANAGERS = [
        'S-137403000' => ['Pasig Station Manager', 'pasig.manager@mobloop.test', '09170004002'],
        'P-137400000' => ['Eastern District Manager', 'eastern.manager@mobloop.test', '09170004001'],
        'P-045800000' => ['Rizal Hub Manager', 'rizal.manager@mobloop.test', '09170004003'],
        'P-043400000' => ['Laguna Hub Manager', 'laguna.manager@mobloop.test', '09170004004'],
        'S-043426000' => ['Santa Cruz Station Manager', 'santacruz.manager@mobloop.test', '09170004005'],
    ];

    /** Map pins, a short walk from Pickrush's: code => [street or building, barangay code, city code, lat, lng]. */
    private const PINS = [
        'S-137403000' => ['Shaw Blvd, corner San Antonio Ave', '137403019', '137403000', 14.5745, 121.0628],
        'P-137400000' => ['C. Raymundo Ave, Caniogan', '137403017', '137403000', 14.5662, 121.0820],
        'P-045800000' => ['Marcos Highway, Mayamot', '045802007', '045802000', 14.6210, 121.1585],
        'P-043400000' => ['National Highway, near Santa Cruz Bypass', '043426002', '043426000', 14.2861, 121.4120],
        'S-043426000' => ['P. Burgos St, near Santa Cruz Plaza', '043426014', '043426000', 14.2802, 121.4160],
    ];

    public function run(): void
    {
        $company = Company::where('name', self::COMPANY)->where('status', 'approved')->first()
            ?? throw new RuntimeException('No approved company named '.self::COMPANY.'. Approve it in Admin → Logistics first.');

        $hubs = DB::transaction(function () use ($company) {
            $hubs = $this->openHubs($company);
            $this->link($company, $hubs);
            foreach (self::MANAGERS as $code => [$name, $email, $mobile]) {
                $this->pin($hubs[$code], ...self::PINS[$code]);
                $this->manager($company, $hubs[$code], $name, $email, $mobile);
                $this->fleet($hubs[$code], $company->name);
            }

            return $hubs;
        });

        $this->rider($hubs['S-137403000'], [
            'first_name' => 'Jerome', 'last_name' => 'Navarro', 'birth_date' => '1997-02-18',
            'mobile' => '9170004011', 'email' => 'pasig.rider@mobloop.test',
            'province_code' => '137400000', 'province_name' => 'Metro Manila',
            'city_code' => '137403000', 'city_name' => 'Pasig',
            'barangay_code' => '137403019', 'barangay_name' => 'San Antonio',
            'street' => 'San Antonio Ave', 'plate_number' => 'NCR 4011', 'license_no' => 'N13-74-004011',
        ]);
        $this->rider($hubs['S-043426000'], [
            'first_name' => 'Rhea', 'last_name' => 'Manalo', 'birth_date' => '1995-07-25',
            'mobile' => '9170004012', 'email' => 'santacruz.rider@mobloop.test',
            'province_code' => '043400000', 'province_name' => 'Laguna',
            'city_code' => '043426000', 'city_name' => 'Santa Cruz',
            'barangay_code' => '043426011', 'barangay_name' => 'Pagsawitan',
            'street' => 'National Highway', 'plate_number' => 'LAG 4012', 'license_no' => 'D04-26-004012',
        ]);
    }

    /** MobLoop's hubs on the route, opening any it does not have yet: code => hub. */
    private function openHubs(Company $company): array
    {
        $creator = app(HubCreator::class);
        $hubs = [];
        foreach (self::PROVINCES as $code) {
            $hubs['P-'.$code] = Hub::ofCompany($company->id)->provinces()->where('psgc_code', $code)->first()
                ?? tap($creator->province($company->id, $code), fn (Hub $hub) => $this->command?->info("Opened {$hub->name}"));
        }
        foreach (self::STATIONS as $code) {
            $hubs['S-'.$code] = Hub::ofCompany($company->id)->stations()->where('psgc_code', $code)->first()
                ?? tap($creator->station($company->id, $code), fn (Hub $hub) => $this->command?->info("Opened {$hub->name}"));
        }

        return $hubs;
    }

    /** Links consecutive province hubs with the same mode and travel time as Pickrush's link. */
    private function link(Company $company, array $hubs): void
    {
        $pickrush = Hub::ofCompany(Company::defaultId())->provinces()->whereIn('psgc_code', self::PROVINCES)->get()->keyBy('psgc_code');

        foreach (array_map(null, array_slice(self::PROVINCES, 0, -1), array_slice(self::PROVINCES, 1)) as [$from, $to]) {
            $theirs = isset($pickrush[$from], $pickrush[$to]) ? HubLink::between($pickrush[$from], $pickrush[$to]) : null;
            [$a, $b] = HubLink::pair($hubs['P-'.$from]->id, $hubs['P-'.$to]->id);
            $link = HubLink::withoutGlobalScopes()->firstOrCreate(['hub_a_id' => $a, 'hub_b_id' => $b], [
                'company_id' => $company->id,
                'mode' => $theirs->mode ?? 'land',
                'travel_hours' => $theirs->travel_hours ?? 2,
                'is_active' => true,
            ]);
            if ($link->wasRecentlyCreated) {
                $this->command?->info("Linked {$hubs['P-'.$from]->name} ↔ {$hubs['P-'.$to]->name}");
            }
        }
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
}
