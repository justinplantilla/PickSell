<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Support\PlaceName;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Loads a town's official barangay list from the PSGC API (the same source the hub forms use for
 * provinces and municipalities) so stations can assign riders to every barangay, not just seeded ones.
 */
class BarangayImporter
{
    /** @return int how many barangays were added */
    public function import(Municipality $municipality): int
    {
        $province = $this->find($this->get('/provinces/'), $municipality->province, 'province');
        $town = $this->find($this->get("/provinces/{$province['code']}/cities-municipalities/"), $municipality->name, 'municipality');
        $existing = Barangay::where('municipality_id', $municipality->id)->pluck('name')
            ->map(fn ($name) => PlaceName::normalize($name));

        $added = 0;
        foreach ($this->get("/cities-municipalities/{$town['code']}/barangays/") as $barangay) {
            if ($existing->contains(PlaceName::normalize($barangay['name']))) {
                continue;
            }
            Barangay::create(['municipality_id' => $municipality->id, 'name' => $barangay['name']]);
            $existing->push(PlaceName::normalize($barangay['name']));
            $added++;
        }

        return $added;
    }

    private function get(string $path): array
    {
        $caBundle = config('services.psgc.ca_bundle');
        try {
            $response = Http::timeout(15)
                ->when($caBundle, fn ($request) => $request->withOptions(['verify' => $caBundle]))
                ->retry(2, 500, throw: false)
                ->acceptJson()
                ->get(rtrim((string) config('services.psgc.url'), '/').$path);
        } catch (ConnectionException $exception) {
            Log::warning('PSGC barangay directory unreachable.', ['path' => $path, 'error' => $exception->getMessage()]);
            $response = null;
        }
        if (! $response?->successful() || ! is_array($response->json())) {
            throw new RuntimeException('The barangay directory (PSGC) could not be reached. Try again later.');
        }

        return $response->json();
    }

    private function find(array $rows, string $name, string $kind): array
    {
        return collect($rows)->first(fn ($row) => PlaceName::same($row['name'] ?? null, $name))
            ?? throw new RuntimeException("“{$name}” was not found in the PSGC {$kind} list. Check the spelling of the station's {$kind}.");
    }
}
