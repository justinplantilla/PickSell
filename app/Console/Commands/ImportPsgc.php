<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Loads database/data/psgc into the geo_* tables. Safe to re-run: rows are upserted by code. */
class ImportPsgc extends Command
{
    protected $signature = 'geo:import-psgc';

    protected $description = 'Load PSGC regions, provinces, municipalities and barangays into the geo tables';

    private const TABLES = [
        'regions' => 'geo_regions',
        'provinces' => 'geo_provinces',
        'municipalities' => 'geo_municipalities',
        'barangays' => 'geo_barangays',
    ];

    public function handle(): int
    {
        foreach (self::TABLES as $file => $table) {
            $rows = self::read($file);
            DB::transaction(function () use ($rows, $table) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->upsert($chunk, ['code']);
                }
            });
            $this->line("  {$table}: ".count($rows).' rows');
        }

        return self::SUCCESS;
    }

    /** @return list<array<string, string>> */
    public static function read(string $file): array
    {
        $lines = explode("\n", trim(gzdecode(file_get_contents(database_path("data/psgc/{$file}.csv.gz")))));
        $header = str_getcsv(array_shift($lines), escape: '');

        return array_map(fn ($line) => array_combine($header, str_getcsv($line, escape: '')), $lines);
    }
}
