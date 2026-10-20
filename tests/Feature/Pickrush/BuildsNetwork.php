<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\HubNetworkBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A small slice of the real PSGC network: Batangas and Laguna (two provinces, Region IV-A) and
 * Metro Manila's Second District. Codes match the PSA list.
 */
trait BuildsNetwork
{
    protected function buildNetwork(): void
    {
        DB::table('geo_regions')->insert([
            ['code' => '040000000', 'name' => 'Region IV-A', 'region_name' => 'CALABARZON'],
            ['code' => '130000000', 'name' => 'NCR', 'region_name' => 'National Capital Region'],
        ]);
        DB::table('geo_provinces')->insert([
            ['code' => '041000000', 'region_code' => '040000000', 'name' => 'Batangas', 'kind' => 'province'],
            ['code' => '043400000', 'region_code' => '040000000', 'name' => 'Laguna', 'kind' => 'province'],
            ['code' => '137400000', 'region_code' => '130000000', 'name' => 'Second District', 'kind' => 'district'],
        ]);
        DB::table('geo_municipalities')->insert([
            ['code' => '041029000', 'province_code' => '041000000', 'region_code' => '040000000', 'name' => 'City of Tanauan', 'is_city' => 1, 'is_capital' => 0],
            ['code' => '041005000', 'province_code' => '041000000', 'region_code' => '040000000', 'name' => 'San Jose', 'is_city' => 0, 'is_capital' => 0],
            ['code' => '043405000', 'province_code' => '043400000', 'region_code' => '040000000', 'name' => 'City of Calamba', 'is_city' => 1, 'is_capital' => 0],
            ['code' => '043426000', 'province_code' => '043400000', 'region_code' => '040000000', 'name' => 'San Pedro', 'is_city' => 1, 'is_capital' => 0],
            ['code' => '137404000', 'province_code' => '137400000', 'region_code' => '130000000', 'name' => 'Quezon City', 'is_city' => 1, 'is_capital' => 0],
        ]);
        DB::table('geo_barangays')->insert([
            ['code' => '041029001', 'municipality_code' => '041029000', 'name' => 'Altura Bata'],
            ['code' => '043405001', 'municipality_code' => '043405000', 'name' => 'Bagong Kalsada'],
            ['code' => '137404001', 'municipality_code' => '137404000', 'name' => 'Bagong Pag-asa'],
        ]);

        app(HubNetworkBuilder::class)->build();
    }

    protected function station(string $municipalityCode): Hub
    {
        return Hub::where('psgc_code', $municipalityCode)->firstOrFail();
    }

    protected function staff(StaffRole $role, ?Hub $hub = null, array $overrides = []): Staff
    {
        static $n = 0;
        $n++;

        return Staff::create(array_merge([
            'full_name' => "Staff Member {$n}",
            'email' => "staff{$n}@pickrush.test",
            'password' => 'secret-password',
            'role' => $role,
            'hub_id' => $hub?->id,
            'is_active' => true,
        ], $overrides));
    }

    protected function pickrushUrl(string $path = '/'): string
    {
        return 'https://'.config('pickrush.domain').'/'.ltrim($path, '/');
    }
}
