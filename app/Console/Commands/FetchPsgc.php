<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the PSGC lists (the same source the address picker uses) and writes compact
 * gzipped CSVs to database/data/psgc. The files are committed, so imports are repeatable
 * and never depend on the API being up. Run again only to pick up a new PSA release.
 */
class FetchPsgc extends Command
{
    protected $signature = 'geo:fetch-psgc {--base=https://psgc.gitlab.io/api} {--from= : Folder of already-downloaded API JSON files (regions.json, ...)}';

    protected $description = 'Download the PSGC lists into database/data/psgc';

    /** Cities PSGC lists without a province, filed under the province they sit in. */
    private const PHYSICAL_PROVINCE = [
        'City of Isabela' => 'Basilan',
        'City of Cotabato' => 'Maguindanao',
    ];

    public function handle(): int
    {
        $get = $this->option('from')
            ? fn (string $path) => json_decode(file_get_contents(rtrim($this->option('from'), '/\\')."/{$path}.json"), true, flags: JSON_THROW_ON_ERROR)
            : fn (string $path) => Http::timeout(300)->retry(3, 2000)->get(rtrim($this->option('base'), '/')."/{$path}/")->throw()->json();

        $this->info('Downloading regions, provinces, districts and cities/municipalities...');
        $regions = $get('regions');
        $provinces = $get('provinces');
        $districts = $get('districts');
        $municipalities = $get('cities-municipalities');
        $this->info('Downloading barangays (about 42,000)...');
        $barangays = $get('barangays');

        $provinceCodeByName = collect($provinces)->pluck('code', 'name');

        $this->write('regions', ['code', 'name', 'region_name'],
            collect($regions)->map(fn ($r) => [$r['code'], $r['name'], $r['regionName'] ?? null]));

        $this->write('provinces', ['code', 'region_code', 'name', 'kind'], collect($provinces)
            ->map(fn ($p) => [$p['code'], $p['regionCode'], $p['name'], 'province'])
            ->concat(collect($districts)->map(fn ($d) => [$d['code'], $d['regionCode'], $d['name'], 'district'])));

        $this->write('municipalities', ['code', 'province_code', 'region_code', 'name', 'is_city', 'is_capital'],
            collect($municipalities)->map(function ($m) use ($provinceCodeByName) {
                $province = $m['provinceCode'] ?: ($m['districtCode'] ?: $provinceCodeByName[self::PHYSICAL_PROVINCE[$m['name']] ?? ''] ?? null);
                if (! $province) {
                    throw new \RuntimeException("No province or district for {$m['name']} ({$m['code']}).");
                }

                return [$m['code'], $province, $m['regionCode'], $m['name'], (int) $m['isCity'], (int) $m['isCapital']];
            }));

        $this->write('barangays', ['code', 'municipality_code', 'name'],
            collect($barangays)->map(fn ($b) => [$b['code'], $b['cityCode'] ?: $b['municipalityCode'], $b['name']]));

        return self::SUCCESS;
    }

    private function write(string $name, array $header, $rows): void
    {
        $path = database_path("data/psgc/{$name}.csv.gz");
        $handle = gzopen($path, 'wb9');
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $header, escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, escape: '');
        }
        rewind($stream);
        gzwrite($handle, stream_get_contents($stream));
        gzclose($handle);
        fclose($stream);
        $this->line("  {$name}: ".count($rows).' rows');
    }
}
