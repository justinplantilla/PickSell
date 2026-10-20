<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Philippine Standard Geographic Code (PSGC) reference data, keyed by the 9-digit PSA code.
 * Shared by the marketplace (addresses) and Pickrush (hubs, zones, routing).
 * Metro Manila has no provinces: its four districts are stored as province-level areas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_regions', function (Blueprint $table) {
            $table->string('code', 9)->primary();
            $table->string('name');
            $table->string('region_name')->nullable();
        });

        Schema::create('geo_provinces', function (Blueprint $table) {
            $table->string('code', 9)->primary();
            $table->string('region_code', 9)->index();
            $table->string('name');
            $table->enum('kind', ['province', 'district'])->default('province');
        });

        Schema::create('geo_municipalities', function (Blueprint $table) {
            $table->string('code', 9)->primary();
            $table->string('province_code', 9)->index();
            $table->string('region_code', 9)->index();
            $table->string('name');
            $table->boolean('is_city')->default(false);
            $table->boolean('is_capital')->default(false);
        });

        Schema::create('geo_barangays', function (Blueprint $table) {
            $table->string('code', 9)->primary();
            $table->string('municipality_code', 9)->index();
            $table->string('name');
        });

        // Marketplace addresses keep their names and also store the codes the address picker selected.
        Schema::table('users', function (Blueprint $table) {
            $table->string('province_code', 9)->nullable()->after('province');
            $table->string('municipality_code', 9)->nullable()->after('municipality');
            $table->string('barangay_code', 9)->nullable()->after('barangay');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'municipality_code', 'barangay_code']);
        });
        Schema::dropIfExists('geo_barangays');
        Schema::dropIfExists('geo_municipalities');
        Schema::dropIfExists('geo_provinces');
        Schema::dropIfExists('geo_regions');
    }
};
