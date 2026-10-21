<?php

namespace App\Pickrush\Services;

use App\Models\Geo\Municipality;
use App\Models\Geo\Province;
use App\Pickrush\Enums\HubType;
use App\Pickrush\Models\Hub;
use Illuminate\Support\Facades\DB;

/**
 * Builds the hub network from the geo tables: one province hub per province (Metro Manila: one
 * per district) and one station hub per city or municipality, under its province hub. Every hub
 * starts active. Re-running only adds missing hubs and refreshes names; addresses, coordinates
 * and the active flag set by HQ are left alone.
 */
class HubNetworkBuilder
{
    /** Metro Manila districts as they are known in operations. */
    private const DISTRICT_NAMES = [
        'First District' => 'Metro Manila Capital District Hub',
        'Second District' => 'Metro Manila Eastern District Hub',
        'Third District' => 'Metro Manila Northern District Hub',
        'Fourth District' => 'Metro Manila Southern District Hub',
    ];

    /** @return array{provinces: int, stations: int, created: int} */
    public function build(): array
    {
        $created = 0;

        DB::transaction(function () use (&$created) {
            $provinceHubs = [];
            foreach (Province::orderBy('code')->get() as $province) {
                $hub = $this->upsert(HubType::Province, $province->code, [
                    'code' => 'P-'.$province->code,
                    'name' => $this->provinceHubName($province),
                    'type' => HubType::Province,
                    'parent_hub_id' => null,
                    'region_code' => $province->region_code,
                    'province_name' => $province->isDistrict() ? 'Metro Manila' : $province->name,
                    'municipality_name' => null,
                ], $created);
                $provinceHubs[$province->code] = $hub;
            }

            $municipalities = Municipality::with('province')->orderBy('code')->get();
            $duplicateNames = $municipalities->countBy(fn ($m) => $this->stationLabel($m->name))->filter(fn ($n) => $n > 1);

            foreach ($municipalities as $municipality) {
                $parent = $provinceHubs[$municipality->province_code] ?? null;
                if (! $parent) {
                    continue;
                }
                $label = $this->stationLabel($municipality->name);
                $provinceName = $municipality->province->isDistrict() ? 'Metro Manila' : $municipality->province->name;

                $this->upsert(HubType::Station, $municipality->code, [
                    'code' => 'S-'.$municipality->code,
                    'name' => $label.' Station'.($duplicateNames->has($label) ? ", {$provinceName}" : ''),
                    'type' => HubType::Station,
                    'parent_hub_id' => $parent->id,
                    'region_code' => $municipality->region_code,
                    'province_name' => $provinceName,
                    'municipality_name' => $municipality->name,
                ], $created);
            }
        });

        return [
            'provinces' => Hub::provinces()->count(),
            'stations' => Hub::stations()->count(),
            'created' => $created,
        ];
    }

    private function upsert(HubType $type, string $psgcCode, array $attributes, int &$created): Hub
    {
        $hub = Hub::firstOrNew(['type' => $type, 'psgc_code' => $psgcCode]);
        $hub->fill($attributes);
        if (! $hub->exists) {
            $hub->is_active = true;
            $created++;
        }
        if ($hub->isDirty()) {
            $hub->save();
        }

        return $hub;
    }

    private function provinceHubName(Province $province): string
    {
        return self::DISTRICT_NAMES[$province->name] ?? "{$province->name} Province Hub";
    }

    /** "City of Tanauan" reads as "Tanauan City"; municipalities keep their name. */
    private function stationLabel(string $name): string
    {
        return preg_match('/^City of (.+)$/', $name, $m) ? "{$m[1]} City" : $name;
    }
}
