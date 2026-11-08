<?php

namespace App\Pickrush\Services\Hq;

use App\Models\Geo\Municipality;
use App\Models\Geo\Province;
use App\Pickrush\Enums\HubType;
use App\Pickrush\Models\Hub;
use App\Pickrush\Services\HubNetworkBuilder;
use App\Pickrush\Services\OperationRefused;

/**
 * A company opens a hub where it operates: a province hub for a province (a Metro Manila
 * district counts as one), or a station hub for a city or municipality under the company's
 * province hub there. Pickrush's nationwide network comes from HubNetworkBuilder instead.
 */
class HubCreator
{
    /** @param  array{name?: ?string, address?: ?string, lat?: ?float, lng?: ?float}  $details */
    public function province(int $companyId, string $provinceCode, array $details = []): Hub
    {
        $province = Province::find($provinceCode) ?? throw new OperationRefused('Pick a province from the list.');
        $this->ensureFree($companyId, HubType::Province, $province->code, "You already have a province hub for {$province->name}.");

        return Hub::create([
            'company_id' => $companyId,
            'code' => 'P-'.$province->code,
            'name' => ($details['name'] ?? null) ?: HubNetworkBuilder::provinceHubName($province),
            'type' => HubType::Province,
            'parent_hub_id' => null,
            'psgc_code' => $province->code,
            'region_code' => $province->region_code,
            'province_name' => $province->isDistrict() ? 'Metro Manila' : $province->name,
            'municipality_name' => null,
            'address' => $details['address'] ?? null,
            'lat' => $details['lat'] ?? null,
            'lng' => $details['lng'] ?? null,
            'is_active' => true,
        ]);
    }

    /** @param  array{name?: ?string, address?: ?string, lat?: ?float, lng?: ?float}  $details */
    public function station(int $companyId, string $municipalityCode, array $details = []): Hub
    {
        $municipality = Municipality::with('province')->find($municipalityCode) ?? throw new OperationRefused('Pick a city or municipality from the list.');
        $parent = Hub::ofCompany($companyId)->provinces()->where('psgc_code', $municipality->province_code)->first()
            ?? throw new OperationRefused("Open a province hub for {$municipality->province->name} first; its stations sort through it.");
        $this->ensureFree($companyId, HubType::Station, $municipality->code, "You already have a station for {$municipality->name}.");

        return Hub::create([
            'company_id' => $companyId,
            'code' => 'S-'.$municipality->code,
            'name' => ($details['name'] ?? null) ?: HubNetworkBuilder::stationLabel($municipality->name).' Station',
            'type' => HubType::Station,
            'parent_hub_id' => $parent->id,
            'psgc_code' => $municipality->code,
            'region_code' => $municipality->region_code,
            'province_name' => $parent->province_name,
            'municipality_name' => $municipality->name,
            'address' => $details['address'] ?? null,
            'lat' => $details['lat'] ?? null,
            'lng' => $details['lng'] ?? null,
            'is_active' => true,
        ]);
    }

    private function ensureFree(int $companyId, HubType $type, string $psgc, string $message): void
    {
        if (Hub::ofCompany($companyId)->where('type', $type)->where('psgc_code', $psgc)->exists()) {
            throw new OperationRefused($message);
        }
    }
}
