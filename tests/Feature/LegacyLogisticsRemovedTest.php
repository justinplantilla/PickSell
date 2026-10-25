<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The 2026_10_20 remove_legacy_logistics migration dropped these users columns; delivery now lives in
 * Pickrush. Queries on them pass on SQLite (an unknown double-quoted identifier is read as a string
 * literal) but fail on MySQL, so feature tests cannot catch a leftover reference. This scan does.
 */
class LegacyLogisticsRemovedTest extends TestCase
{
    private const DROPPED_USER_COLUMNS = ['provider_type', 'vehicle_type', 'plate_number', 'or_cr_upload', 'delivery_area', 'assigned_hub_id'];

    public function test_marketplace_code_no_longer_references_dropped_user_columns(): void
    {
        $offenders = [];
        foreach ([app_path(), resource_path('views'), base_path('routes')] as $root) {
            foreach (File::allFiles($root) as $file) {
                // Pickrush keeps its own pr_* tables and may use the same words for its own columns.
                if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Pickrush'.DIRECTORY_SEPARATOR)
                    || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'pickrush')) {
                    continue;
                }
                $contents = $file->getContents();
                foreach (self::DROPPED_USER_COLUMNS as $column) {
                    if (preg_match('/[\'"$>]'.$column.'\b/', $contents)) {
                        $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).": {$column}";
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'These files still use users columns dropped with the legacy logistics module.');
    }
}
