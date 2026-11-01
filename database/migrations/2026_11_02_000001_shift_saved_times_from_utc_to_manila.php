<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The app ran on UTC until it switched to Asia/Manila (config/app.php), so every date/time saved
 * before then is 8 hours behind Philippine time. Manila has no daylight saving: a flat +8 hours
 * puts the old rows on the new clock. MySQL only; other databases (the SQLite test runs) start
 * empty on the new clock.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->shift('+');
    }

    public function down(): void
    {
        $this->shift('-');
    }

    private function shift(string $sign): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $columns = DB::select("select table_name as t, column_name as c from information_schema.columns
            where table_schema = database() and data_type in ('datetime', 'timestamp') and table_name <> 'migrations'");

        DB::transaction(function () use ($columns, $sign) {
            foreach ($columns as $col) {
                DB::update("update `{$col->t}` set `{$col->c}` = `{$col->c}` {$sign} interval 8 hour where `{$col->c}` is not null");
            }
        });
    }
};
