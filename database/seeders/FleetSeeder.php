<?php

namespace Database\Seeders;

use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample company trucks and hired drivers for every hub that has real staff: one truck and one
 * driver per station, three of each per province hub (each driver is their truck's default).
 *
 *   php artisan db:seed --class=FleetSeeder
 *
 * Seeded drivers have no license photos; the hub manager uploads them under Trucks & drivers.
 * Safe to run again: a hub that already has a truck or a driver is left as it is.
 */
class FleetSeeder extends Seeder
{
    private const DRIVERS = [
        'Ramon Dizon', 'Eduardo Santos', 'Rogelio Bautista', 'Danilo Reyes', 'Arnel Villanueva', 'Jun Mendoza',
        'Romeo Castillo', 'Nestor Aquino', 'Bernardo Ramos', 'Wilfredo Garcia', 'Rodel Navarro', 'Ernesto Flores',
        'Alfredo Torres', 'Reynaldo Cruz', 'Rolando Gonzales', 'Felix Manalo', 'Mario Salazar', 'Ricardo Lopez',
    ];

    public function run(): void
    {
        $hubs = Hub::whereHas('staff', fn ($q) => $q->where('is_sample', false)->where('is_active', true))->orderBy('code')->get();
        $company = config('pickrush.fleet.company_name');
        $n = 0;
        $seeded = 0;

        DB::transaction(function () use ($hubs, $company, &$n, &$seeded) {
            foreach ($hubs as $hub) {
                $hasFleet = Vehicle::withoutGlobalScopes()->where('hub_id', $hub->id)->exists()
                    || Driver::withoutGlobalScopes()->where('hub_id', $hub->id)->exists();
                if ($hasFleet) {
                    $this->command?->warn("{$hub->name} already has trucks or drivers; skipped.");

                    continue;
                }

                for ($i = 1; $i <= Vehicle::limitFor($hub); $i++) {
                    $n++;
                    $driver = Driver::withoutGlobalScopes()->create([
                        'hub_id' => $hub->id,
                        'first_name' => strtok(self::DRIVERS[($n - 1) % count(self::DRIVERS)], ' '),
                        'last_name' => substr(strrchr(self::DRIVERS[($n - 1) % count(self::DRIVERS)], ' '), 1),
                        'birthdate' => today()->subYears(28 + $n % 20)->subDays($n * 11)->toDateString(),
                        'mobile_no' => sprintf('0918%07d', $hub->id * 10 + $i),
                        'license_no' => sprintf('D%02d-%02d-%06d', $i, 20 + $i, $hub->id),
                        'license_codes' => $hub->isProvince() ? 'B,B2,C,CE' : 'B,B2,C',
                        'license_expiry' => today()->addYears(3)->toDateString(),
                        'is_active' => true,
                    ]);
                    Vehicle::withoutGlobalScopes()->create([
                        'hub_id' => $hub->id,
                        'plate_no' => sprintf('PR%s %04d', chr(64 + $i), $hub->id),
                        'vehicle_type' => 'truck',
                        'registered_owner' => $company,
                        'capacity_parcels' => $hub->isProvince() ? 1500 : 500,
                        'default_driver_id' => $driver->id,
                        'is_active' => true,
                    ]);
                }
                $seeded++;
            }
        });

        $this->command?->info("Seeded {$n} trucks and drivers across {$seeded} hubs.");
    }
}
