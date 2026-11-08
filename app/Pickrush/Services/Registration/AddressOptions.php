<?php

namespace App\Pickrush\Services\Registration;

use App\Models\Geo\Barangay;
use App\Models\Geo\Municipality;
use App\Models\Geo\Province;

/**
 * The business address dropdowns, from the PSGC tables Pickrush routes with. Metro Manila has
 * districts rather than provinces, so it is offered as one "province" (code NCR) whose cities are
 * every city in the region.
 */
class AddressOptions
{
    public const METRO_MANILA = 'NCR';

    private const NCR_REGION = '130000000';

    /** @return list<array{code: string, name: string}> */
    public function provinces(): array
    {
        $provinces = Province::where('kind', 'province')->orderBy('name')->get(['code', 'name'])
            ->map(fn ($p) => ['code' => $p->code, 'name' => $p->name]);

        if (Municipality::where('region_code', self::NCR_REGION)->exists()) {
            $provinces->push(['code' => self::METRO_MANILA, 'name' => 'Metro Manila']);
        }

        return $provinces->sortBy('name')->values()->all();
    }

    /** @return list<array{code: string, name: string}> */
    public function municipalities(string $province): array
    {
        return $this->municipalityQuery($province)->orderBy('name')->get(['code', 'name'])
            ->map(fn ($m) => ['code' => $m->code, 'name' => $m->name])->all();
    }

    /** @return list<array{code: string, name: string}> */
    public function barangays(string $municipality): array
    {
        return Barangay::where('municipality_code', $municipality)->orderBy('name')->get(['code', 'name'])
            ->map(fn ($b) => ['code' => $b->code, 'name' => $b->name])->all();
    }

    public function validProvince(string $province): bool
    {
        return $province === self::METRO_MANILA
            ? Municipality::where('region_code', self::NCR_REGION)->exists()
            : Province::where('kind', 'province')->whereKey($province)->exists();
    }

    public function validMunicipality(string $province, string $municipality): bool
    {
        return $this->municipalityQuery($province)->whereKey($municipality)->exists();
    }

    public function validBarangay(string $municipality, string $barangay): bool
    {
        return Barangay::where('municipality_code', $municipality)->whereKey($barangay)->exists();
    }

    private function municipalityQuery(string $province)
    {
        return $province === self::METRO_MANILA
            ? Municipality::where('region_code', self::NCR_REGION)
            : Municipality::where('province_code', $province);
    }
}
