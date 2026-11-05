<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the rider app last talked to the server. An on-shift rider whose app has been closed
 * longer than Rider::IDLE_MINUTES is treated as off duty and switched off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_riders', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('shift_status');
        });
    }

    public function down(): void
    {
        Schema::table('pr_riders', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
