<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The station address riders see, picked from the PSGC list (city in the station's province,
 * then a barangay) plus the hub manager's own details (street, building, gate). The composed
 * text stays in pr_hubs.address for everything that already reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_hubs', function (Blueprint $table) {
            $table->string('address_city_code', 9)->nullable()->after('address');
            $table->string('address_barangay_code', 9)->nullable()->after('address_city_code');
            $table->string('address_line')->nullable()->after('address_barangay_code');   // street, building, gate
        });
    }

    public function down(): void
    {
        Schema::table('pr_hubs', function (Blueprint $table) {
            $table->dropColumn(['address_city_code', 'address_barangay_code', 'address_line']);
        });
    }
};
