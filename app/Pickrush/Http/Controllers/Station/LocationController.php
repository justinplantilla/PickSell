<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Models\Geo\Barangay;
use App\Models\Geo\Municipality;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\RiderReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Where the station is: its address and map pin. Riders navigate to the pin and can only say
 * "Arrived at hub" near it. The address starts from the station's own city (any city of its
 * province can be picked), then a barangay of that city, then the hub manager's details. The
 * hub manager sets it; everyone at the station can see it.
 */
class LocationController extends StationController
{
    public function show()
    {
        $station = $this->station();
        $home = Municipality::find($station->psgc_code);
        $cityCode = $station->address_city_code ?? $home?->code;

        return view('pickrush.station.location', [
            'station' => $station,
            'canEdit' => $this->staff()->role === StaffRole::HubManager,
            'province' => $home?->province,
            'cities' => $home ? Municipality::where('province_code', $home->province_code)->orderBy('name')->get(['code', 'name']) : collect(),
            'cityCode' => $cityCode,
            'barangays' => $cityCode ? self::barangaysOf($cityCode) : collect(),
        ]);
    }

    /** Barangays of a city in the station's province, for the address picker. */
    public function barangays(Request $request): JsonResponse
    {
        $data = $request->validate(['city' => ['required', 'string', Rule::in($this->provinceCityCodes())]]);

        return response()->json(['barangays' => self::barangaysOf($data['city'])->map(fn ($b) => ['code' => $b->code, 'name' => $b->name])->values()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'address_city_code' => ['required', 'string', Rule::in($this->provinceCityCodes())],
            'address_barangay_code' => ['required', 'string', Rule::exists('geo_barangays', 'code')->where('municipality_code', $request->input('address_city_code'))],
            'address_line' => 'required|string|max:150',
            'lat' => 'required|numeric|between:4,22',     // the Philippines
            'lng' => 'required|numeric|between:116,127',
        ], [
            'address_barangay_code.required' => 'Pick the barangay.',
            'address_barangay_code.exists' => 'Pick a barangay of the selected city.',
            'address_line.required' => 'Add the street, building or gate riders should look for.',
            'lat.required' => 'Drop a pin on the map first.',
        ]);
        $station = $this->station();
        $data['address'] = self::compose($station, $data['address_line'], $data['address_barangay_code'], $data['address_city_code']);

        return $this->attempt(fn () => $station->update($data), 'Station location saved. Riders now navigate to this pin.');
    }

    /** A rider's reported problem was handled. */
    public function resolveReport(RiderReport $report)
    {
        abort_unless($report->station_hub_id === $this->station()->id, 404);

        return $this->attempt(fn () => $report->update(['resolved_by' => $this->staff()->id, 'resolved_at' => now()]), 'Marked as handled.');
    }

    /** "Rider Zone, 123 Osmeña Blvd., Brgy. Kamputhaw, City of Cebu, Cebu" */
    public static function compose(Hub $station, string $line, string $barangayCode, string $cityCode): string
    {
        $barangay = Barangay::find($barangayCode)?->name;
        $city = Municipality::with('province')->find($cityCode);

        return collect([trim($line), $barangay ? "Brgy. {$barangay}" : null, $city?->name, $city?->province?->name ?? $station->province_name])
            ->filter()->join(', ');
    }

    /** @return list<string> city codes of the station's province */
    private function provinceCityCodes(): array
    {
        $home = Municipality::find($this->station()->psgc_code);

        return $home ? Municipality::where('province_code', $home->province_code)->pluck('code')->all() : [];
    }

    private static function barangaysOf(string $cityCode)
    {
        return Barangay::where('municipality_code', $cityCode)->orderBy('name')->get(['code', 'name']);
    }
}
