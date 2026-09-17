<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support ALTER COLUMN for enums, so we update via raw
        // Just ensure 'deactivated' is accepted — SQLite enums are stored as TEXT
        // No schema change needed for SQLite; enum is not enforced at DB level
        // We only need to ensure the app accepts 'deactivated' as a valid status
    }

    public function down(): void {}
};
