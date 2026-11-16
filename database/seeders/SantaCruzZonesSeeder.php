<?php

namespace Database\Seeders;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Zone;
use App\Pickrush\Services\Rider\RiderApplication;
use App\Pickrush\Services\Station\RiderOnboarding;
use App\Pickrush\Services\Station\ZoneSetup;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Six zones at Pickrush's Santa Cruz Station, Laguna, each with its own rider, to demo zoning.
 * Riders go through the real flow (rider-app application, then approval by the station manager);
 * zones are saved through ZoneSetup as the manager. Each zone's backup is the next zone's rider.
 * The existing santacruz.rider@pickrush.test is the Poblacion default.
 *
 *   php artisan db:seed --class=SantaCruzZonesSeeder
 *
 * Riders sign in as santacruz.<zone>@pickrush.test / Rider@1234. Safe to run again.
 */
class SantaCruzZonesSeeder extends Seeder
{
    private const PASSWORD = 'Rider@1234';

    /** zone => [rider key, first name, last name, vehicle, barangay codes (043426xxx suffix)] */
    private const ZONES = [
        'Poblacion' => [null, null, null, null, ['014', '015', '016', '017', '018', '023']],
        'Bagumbayan–Calios' => ['bagumbayan', 'Mark', 'Villanueva', 'motorcycle', ['002', '004', '011']],
        'Lakeshore' => ['lakeshore', 'Paolo', 'Reyes', 'motorcycle', ['006', '003', '008']],
        'Patimbao–Malinao' => ['patimbao', 'Jerome', 'Santos', 'motorcycle', ['013', '009', '005']],
        'Santo Angel–San Pablo' => ['santoangel', 'Ramil', 'Garcia', 'motorcycle', ['025', '024', '026', '021', '022']],
        'Eastern Uplands' => ['uplands', 'Dennis', 'Mendoza', 'four_wheel', ['001', '019', '020', '012', '010', '007']],
    ];

    public function run(): void
    {
        $station = Hub::ofCompany(Company::defaultId())->where('code', 'S-043426000')->firstOrFail();
        $manager = Staff::where('hub_id', $station->id)->where('role', StaffRole::HubManager)->where('is_active', true)->first()
            ?? throw new RuntimeException('Santa Cruz Station needs an active hub manager.');
        auth('pickrush')->setUser($manager);

        $riders = [];
        $n = 0;
        foreach (self::ZONES as $zone => [$key, $first, $last, $vehicle, $codes]) {
            $n++;
            $riders[$zone] = $key
                ? $this->rider($station, $manager, $key, $first, $last, $vehicle, $codes[0], $n)
                : Rider::withoutGlobalScopes()->where('email', 'santacruz.rider@pickrush.test')->firstOrFail();
        }

        $names = array_keys(self::ZONES);
        foreach ($names as $i => $name) {
            $backup = $riders[$names[($i + 1) % count($names)]];
            $existing = Zone::withoutGlobalScopes()->where('station_hub_id', $station->id)->where('name', $name)->first();
            app(ZoneSetup::class)->save($existing, [
                'name' => $name,
                'default_rider_id' => $riders[$name]->id,
                'backup_rider_id' => $backup->id,
            ], array_map(fn ($c) => '043426'.$c, self::ZONES[$name][4]), $manager);
            $this->command?->info("{$name}: {$riders[$name]->email} (backup {$backup->email})");
        }
    }

    private function rider(Hub $station, Staff $manager, string $key, string $first, string $last, string $vehicle, string $barangay, int $n): Rider
    {
        $email = "santacruz.{$key}@pickrush.test";
        if ($rider = Rider::withoutGlobalScopes()->where('email', $email)->first()) {
            return $rider;
        }

        $rider = app(RiderApplication::class)->apply([
            'hub_id' => $station->id,
            'first_name' => $first,
            'last_name' => $last,
            'birth_date' => '1995-0'.$n.'-1'.$n,
            'mobile' => '91704260'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'email' => $email,
            'password' => self::PASSWORD,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426'.$barangay,
            'barangay_name' => \App\Models\Geo\Barangay::where('code', '043426'.$barangay)->value('name'),
            'street' => 'Purok '.$n,
            'vehicle_type' => $vehicle,
            'plate_number' => 'SCZ 42'.$n.$n,
            'license_no' => 'D04-26-0000'.$n.$n,
            'license_expiry' => now()->addYears(3)->toDateString(),
            'orcr_expiry' => now()->addYear()->toDateString(),
        ], [
            'license_front' => UploadedFile::fake()->create('license-front.jpg', 40, 'image/jpeg'),
            'license_back' => UploadedFile::fake()->create('license-back.jpg', 40, 'image/jpeg'),
            'orcr' => [UploadedFile::fake()->create('or-cr.jpg', 40, 'image/jpeg')],
        ]);
        app(RiderOnboarding::class)->approve($rider, $manager);

        return $rider->refresh();
    }
}
