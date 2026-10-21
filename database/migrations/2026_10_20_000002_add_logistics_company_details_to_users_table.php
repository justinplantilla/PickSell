<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics provider registration asks for the SEC registration number (companies), the region of the
 * company address and the regions the provider serves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sec_registration_no', 11)->nullable()->unique()->after('tin');
            $table->string('region', 100)->nullable()->after('house_no');
            $table->json('service_areas')->nullable()->after('sec_registration_no');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['sec_registration_no']);
            $table->dropColumn(['sec_registration_no', 'region', 'service_areas']);
        });
    }
};
