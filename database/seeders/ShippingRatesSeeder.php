<?php

namespace Database\Seeders;

use App\Pickrush\Services\Shipping\RouteRateGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pickrush's marketplace rate card, the province-to-region map and the parcel classes. Safe to
 * re-run: existing rows are left alone, so HQ's edits survive.
 *
 * The generic prices are a Metro Manila-origin price list (confirmed by the Pickrush team),
 * taken from Flash Express rates as a reference only: Pickrush is the carrier. They become the
 * five route price levels, and the 5 × 5 route grid below generates a price for every
 * (seller region → buyer region) pair. HEAVY ₱25 and BULKY ₱50 rider pay are proposals.
 */
class ShippingRatesSeeder extends Seeder
{
    public const EFFECTIVE_FROM = '2026-04-01';

    /** Generic fee by destination region for each weight tier (kg). */
    private const TIERS = [0.5, 1, 2, 3, 5, 10];

    private const FEES = [
        'NCR' => [60, 75, 95, 115, 155, 280],
        'LUZON' => [75, 85, 105, 125, 170, 310],
        'VISAYAS' => [85, 95, 115, 135, 180, 330],
        'MINDANAO' => [90, 100, 120, 140, 190, 345],
        'ISLAND' => [98, 110, 130, 152, 205, 370],
    ];

    private const PER_KG_ABOVE_10 = ['NCR' => 25, 'LUZON' => 28, 'VISAYAS' => 30, 'MINDANAO' => 31, 'ISLAND' => 33];

    /** Price level k uses the generic prices to this destination (the NCR-origin price list). */
    private const LEVELS = [
        0 => ['NCR', 'Local (within one region)'],
        1 => ['LUZON', 'Nearby / provincial'],
        2 => ['VISAYAS', 'Inter-island, mid distance'],
        3 => ['MINDANAO', 'Far'],
        4 => ['ISLAND', 'Remote islands'],
    ];

    /**
     * Route level: seller's region (row) → buyer's region (column). Symmetric. Within a region is
     * local (0), except between island provinces, which are not connected by land (3).
     */
    private const ROUTE_LEVELS = [
        'NCR' => ['NCR' => 0, 'LUZON' => 1, 'VISAYAS' => 2, 'MINDANAO' => 3, 'ISLAND' => 4],
        'LUZON' => ['NCR' => 1, 'LUZON' => 0, 'VISAYAS' => 2, 'MINDANAO' => 3, 'ISLAND' => 4],
        'VISAYAS' => ['NCR' => 2, 'LUZON' => 2, 'VISAYAS' => 0, 'MINDANAO' => 2, 'ISLAND' => 3],
        'MINDANAO' => ['NCR' => 3, 'LUZON' => 3, 'VISAYAS' => 2, 'MINDANAO' => 0, 'ISLAND' => 4],
        'ISLAND' => ['NCR' => 4, 'LUZON' => 4, 'VISAYAS' => 3, 'MINDANAO' => 4, 'ISLAND' => 3],
    ];

    /** code => [above kg, up to kg, rider pay] */
    private const CLASSES = [
        'STANDARD' => [0, 10, 15],
        'HEAVY' => [10, 20, 25],
        'BULKY' => [20, 50, 50],
    ];

    private const REGIONS = [
        'NCR' => 'Metro Manila',
        'LUZON' => 'Luzon',
        'VISAYAS' => 'Visayas',
        'MINDANAO' => 'Mindanao',
        'ISLAND' => 'Island provinces',
    ];

    /**
     * Names are matched ignoring case, extra spaces and ñ/n, so only one spelling is stored.
     * Metro Manila also lists its cities (addresses stored by city) and the PSGC district
     * names the address picker saves as the "province".
     */
    private const PROVINCES = [
        'NCR' => [
            'Metro Manila', 'NCR', 'National Capital Region',
            'First District', 'Second District', 'Third District', 'Fourth District',
            'Caloocan', 'Las Pinas', 'Makati', 'Malabon', 'Mandaluyong', 'Manila', 'Marikina',
            'Muntinlupa', 'Navotas', 'Paranaque', 'Pasay', 'Pasig', 'Pateros', 'Quezon City',
            'San Juan', 'Taguig', 'Valenzuela',
            'City of Caloocan', 'City of Las Pinas', 'City of Makati', 'City of Malabon',
            'City of Mandaluyong', 'City of Manila', 'City of Marikina', 'City of Muntinlupa',
            'City of Navotas', 'City of Paranaque', 'Pasay City', 'City of Pasig', 'City of San Juan',
            'City of Taguig', 'City of Valenzuela',
        ],
        'LUZON' => [
            'Abra', 'Albay', 'Apayao', 'Aurora', 'Bataan', 'Batangas', 'Benguet', 'Bulacan', 'Cagayan',
            'Camarines Norte', 'Camarines Sur', 'Cavite', 'Ifugao', 'Ilocos Norte', 'Ilocos Sur',
            'Isabela', 'Kalinga', 'La Union', 'Laguna', 'Mountain Province', 'Nueva Ecija',
            'Nueva Vizcaya', 'Occidental Mindoro', 'Oriental Mindoro', 'Pampanga', 'Pangasinan',
            'Quezon', 'Quirino', 'Rizal', 'Sorsogon', 'Tarlac', 'Zambales',
        ],
        'VISAYAS' => [
            'Aklan', 'Antique', 'Bohol', 'Capiz', 'Cebu', 'Eastern Samar', 'Iloilo', 'Leyte',
            'Negros Occidental', 'Negros Oriental', 'Northern Samar', 'Samar', 'Southern Leyte',
        ],
        'MINDANAO' => [
            'Agusan del Norte', 'Agusan del Sur', 'Bukidnon', 'Cotabato', 'Davao de Oro',
            'Davao del Norte', 'Davao del Sur', 'Davao Occidental', 'Davao Oriental',
            'Lanao del Norte', 'Lanao del Sur', 'Maguindanao del Norte', 'Maguindanao del Sur',
            'Maguindanao', // PSGC data still lists the undivided province
            'Misamis Occidental', 'Misamis Oriental', 'Sarangani', 'South Cotabato', 'Sultan Kudarat',
            'Surigao del Norte', 'Surigao del Sur', 'Zamboanga del Norte', 'Zamboanga del Sur',
            'Zamboanga Sibugay',
        ],
        'ISLAND' => [
            'Batanes', 'Biliran', 'Camiguin', 'Catanduanes', 'Dinagat Islands', 'Guimaras',
            'Marinduque', 'Masbate', 'Palawan', 'Romblon', 'Siquijor', 'Basilan', 'Sulu', 'Tawi-Tawi',
        ],
    ];

    public function run(): void
    {
        $now = now();

        DB::table('pr_shipping_regions')->insertOrIgnore(
            collect(self::REGIONS)->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values()->all()
        );

        DB::table('pr_shipping_region_provinces')->insertOrIgnore(
            collect(self::PROVINCES)->flatMap(fn ($names, $region) => array_map(
                fn ($name) => ['name' => $name, 'region_code' => $region], $names
            ))->all()
        );

        if (! DB::table('pr_shipping_rates')->whereNull('origin_region')->exists()) {
            $rows = [];
            foreach (self::FEES as $region => $fees) {
                foreach (self::TIERS as $i => $kg) {
                    $rows[] = [
                        'origin_region' => null, 'destination_region' => $region,
                        'max_weight_kg' => $kg, 'fee' => $fees[$i],
                        'effective_from' => self::EFFECTIVE_FROM, 'effective_to' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            DB::table('pr_shipping_rates')->insert($rows);
        }

        if (! DB::table('pr_shipping_overweight_rates')->whereNull('origin_region')->exists()) {
            DB::table('pr_shipping_overweight_rates')->insert(collect(self::PER_KG_ABOVE_10)->map(fn ($fee, $region) => [
                'origin_region' => null, 'destination_region' => $region, 'over_kg' => 10, 'per_kg_fee' => $fee,
                'effective_from' => self::EFFECTIVE_FROM, 'effective_to' => null,
                'created_at' => $now, 'updated_at' => $now,
            ])->values()->all());
        }

        DB::table('pr_parcel_classes')->insertOrIgnore(collect(self::CLASSES)->map(fn ($c, $code) => [
            'code' => $code, 'min_kg_exclusive' => $c[0], 'max_kg_inclusive' => $c[1],
            'rider_pay' => $c[2], 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ])->values()->all());

        $this->seedRouteLevels();
        app(RouteRateGenerator::class)->generate(RouteRateGenerator::FIRST_ROUTE_DATE);
    }

    /** The five price levels, copied from the generic rows so level 0 equals the NCR-origin list exactly. */
    private function seedRouteLevels(): void
    {
        if (! DB::table('pr_shipping_levels')->exists()) {
            $now = now();
            foreach (self::LEVELS as $level => [$region, $name]) {
                $perKg = DB::table('pr_shipping_overweight_rates')->whereNull('origin_region')->where('destination_region', $region)
                    ->orderByDesc('effective_from')->first();
                DB::table('pr_shipping_levels')->insert(['level' => $level, 'name' => $name, 'over_kg' => $perKg->over_kg,
                    'per_kg_fee' => $perKg->per_kg_fee, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('pr_shipping_level_fees')->insert(DB::table('pr_shipping_rates')->whereNull('origin_region')
                    ->where('destination_region', $region)->whereNull('effective_to')->orderBy('max_weight_kg')->get()
                    ->map(fn ($row) => ['level' => $level, 'max_weight_kg' => $row->max_weight_kg, 'fee' => $row->fee])->all());
            }
        }

        DB::table('pr_shipping_route_levels')->insertOrIgnore(collect(self::ROUTE_LEVELS)->flatMap(fn ($row, $origin) => collect($row)
            ->map(fn ($level, $destination) => ['origin_region' => $origin, 'destination_region' => $destination, 'level' => $level, 'updated_at' => now()])
            ->values())->all());
    }
}
