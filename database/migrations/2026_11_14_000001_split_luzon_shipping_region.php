<?php

use App\Pickrush\Services\Shipping\RegionResolver;
use App\Pickrush\Services\Shipping\RouteRateGenerator;
use Database\Seeders\ShippingRatesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the LUZON shipping region into North Luzon (N_LUZON) and South Luzon (S_LUZON). Each
 * company's LUZON grid row and column is copied to both halves, keeping its edits; then North ↔
 * South becomes level 2 (it passes through Metro Manila) and North Luzon goes one level up to the
 * Visayas and Mindanao. LUZON rate rows, history included, are copied the same way, and the
 * changed routes get new prices from today, so placed orders keep their price.
 *
 * A fresh database already seeds the two halves, so there is nothing to split.
 */
return new class extends Migration
{
    private const NORTH = 'N_LUZON';

    private const SOUTH = 'S_LUZON';

    /** North Luzon is one level further from these than South Luzon. */
    private const FURTHER_FROM_NORTH = ['VISAYAS', 'MINDANAO'];

    private const NORTH_SOUTH_LEVEL = 2;

    private const RATE_TABLES = ['pr_shipping_rates', 'pr_shipping_overweight_rates'];

    public function up(): void
    {
        if (! DB::table('pr_shipping_regions')->where('code', 'LUZON')->exists()) {
            return;
        }

        DB::transaction(function () {
            DB::table('pr_shipping_regions')->insertOrIgnore([
                ['code' => self::NORTH, 'name' => 'North Luzon'],
                ['code' => self::SOUTH, 'name' => 'South Luzon'],
            ]);

            // Provinces HQ added to LUZON that are not on the North list ship as South Luzon.
            $north = array_map(fn ($name) => RegionResolver::key($name), ShippingRatesSeeder::NORTH_LUZON);
            foreach (DB::table('pr_shipping_region_provinces')->where('region_code', 'LUZON')->get() as $row) {
                DB::table('pr_shipping_region_provinces')->where('name', $row->name)
                    ->update(['region_code' => in_array(RegionResolver::key($row->name), $north, true) ? self::NORTH : self::SOUTH]);
            }

            $this->splitGrid();
            foreach (self::RATE_TABLES as $table) {
                $this->splitRates($table);
            }
            DB::table('pr_shipping_regions')->where('code', 'LUZON')->delete();

            $today = now()->toDateString();
            foreach (DB::table('pr_shipping_route_levels')->distinct()->pluck('company_id') as $company) {
                app(RouteRateGenerator::class)->forCompany((int) $company)->generate($today);
            }
        });
    }

    private function splitGrid(): void
    {
        $rows = DB::table('pr_shipping_route_levels')->where(fn ($q) => $q->where('origin_region', 'LUZON')->orWhere('destination_region', 'LUZON'))->get();
        $highest = DB::table('pr_shipping_levels')->groupBy('company_id')->selectRaw('company_id, max(level) as level')->pluck('level', 'company_id');

        $new = [];
        foreach ($rows as $row) {
            foreach ($this->halves($row->origin_region) as $origin) {
                foreach ($this->halves($row->destination_region) as $destination) {
                    $top = (int) $highest[$row->company_id];
                    $level = (int) $row->level;
                    if ([$origin, $destination] === [self::NORTH, self::SOUTH] || [$origin, $destination] === [self::SOUTH, self::NORTH]) {
                        $level = min(self::NORTH_SOUTH_LEVEL, $top);
                    } elseif (($origin === self::NORTH && in_array($destination, self::FURTHER_FROM_NORTH, true))
                        || ($destination === self::NORTH && in_array($origin, self::FURTHER_FROM_NORTH, true))) {
                        $level = min($level + 1, $top);
                    }
                    $new[] = ['company_id' => $row->company_id, 'origin_region' => $origin, 'destination_region' => $destination,
                        'level' => $level, 'updated_at' => now()];
                }
            }
        }

        DB::table('pr_shipping_route_levels')->where(fn ($q) => $q->where('origin_region', 'LUZON')->orWhere('destination_region', 'LUZON'))->delete();
        foreach (array_chunk($new, 200) as $chunk) {
            DB::table('pr_shipping_route_levels')->insert($chunk);
        }
    }

    /** Copies every LUZON row to both halves; North ↔ South has no old price and starts today. */
    private function splitRates(string $table): void
    {
        $luzon = fn ($q) => $q->where('origin_region', 'LUZON')->orWhere('destination_region', 'LUZON');

        $new = [];
        foreach (DB::table($table)->where($luzon)->get() as $row) {
            $origins = $row->origin_region === null ? [null] : $this->halves($row->origin_region);
            foreach ($origins as $origin) {
                foreach ($this->halves($row->destination_region) as $destination) {
                    if ($origin !== null && $row->origin_region === 'LUZON' && $row->destination_region === 'LUZON' && $origin !== $destination) {
                        continue;
                    }
                    $new[] = array_merge(array_diff_key((array) $row, ['id' => true]), ['origin_region' => $origin, 'destination_region' => $destination]);
                }
            }
        }

        DB::table($table)->where($luzon)->delete();
        foreach (array_chunk($new, 200) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /** @return list<string> */
    private function halves(string $region): array
    {
        return $region === 'LUZON' ? [self::NORTH, self::SOUTH] : [$region];
    }

    /** Back to one LUZON region priced as South Luzon was; North Luzon's own prices are dropped. */
    public function down(): void
    {
        if (! DB::table('pr_shipping_regions')->where('code', self::SOUTH)->exists()) {
            return;
        }

        DB::transaction(function () {
            DB::table('pr_shipping_regions')->insertOrIgnore(['code' => 'LUZON', 'name' => 'Luzon']);
            DB::table('pr_shipping_region_provinces')->whereIn('region_code', [self::NORTH, self::SOUTH])->update(['region_code' => 'LUZON']);

            $halves = fn ($q) => $q->whereIn('origin_region', [self::NORTH, self::SOUTH])->orWhereIn('destination_region', [self::NORTH, self::SOUTH]);
            $south = fn (?string $region) => $region === self::SOUTH ? 'LUZON' : $region;
            $keep = fn ($row) => $row->origin_region !== self::NORTH && $row->destination_region !== self::NORTH;

            $grid = DB::table('pr_shipping_route_levels')->where($halves)->get()->filter($keep)
                ->map(fn ($row) => array_merge((array) $row, ['origin_region' => $south($row->origin_region), 'destination_region' => $south($row->destination_region)]))
                ->values()->all();
            DB::table('pr_shipping_route_levels')->where($halves)->delete();
            foreach (array_chunk($grid, 200) as $chunk) {
                DB::table('pr_shipping_route_levels')->insert($chunk);
            }

            foreach (self::RATE_TABLES as $table) {
                $rows = DB::table($table)->where($halves)->get()->filter($keep)
                    ->map(fn ($row) => array_merge(array_diff_key((array) $row, ['id' => true]),
                        ['origin_region' => $south($row->origin_region), 'destination_region' => $south($row->destination_region)]))
                    ->values()->all();
                DB::table($table)->where($halves)->delete();
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }

            DB::table('pr_shipping_regions')->whereIn('code', [self::NORTH, self::SOUTH])->delete();
        });
    }
};
