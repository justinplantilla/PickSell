<?php

use Database\Seeders\ShippingRatesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace shipping prices, owned by Pickrush (the only carrier): weight-tier fees and a
 * per-kg rate above the heaviest tier, by (seller's region, buyer's region), the province-to-
 * region map, and parcel classes with the rider's pay. A row without an origin is the generic
 * price to that destination, used when the route has no row of its own.
 *
 * Route rows are generated from five price levels and a 5 × 5 grid saying which level each
 * route is (RouteRateGenerator). Every rate row is dated so old orders keep the price they
 * were quoted: a change ends a row and adds a new one instead of editing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_shipping_regions', function (Blueprint $table) {
            $table->string('code', 10)->primary();               // NCR, LUZON, VISAYAS, MINDANAO, ISLAND
            $table->string('name');
        });

        // Province names and aliases (Metro Manila cities, PSGC district names) per region.
        Schema::create('pr_shipping_region_provinces', function (Blueprint $table) {
            $table->string('name', 100)->primary();
            $table->string('region_code', 10);
            $table->foreign('region_code')->references('code')->on('pr_shipping_regions');
        });

        Schema::create('pr_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('origin_region', 10)->nullable();     // null: generic price to the destination
            $table->string('destination_region', 10);
            $table->decimal('max_weight_kg', 6, 2);              // the tier: fee applies up to this weight
            $table->decimal('fee', 10, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();            // last day the row applies; null: open
            $table->timestamps();
            $table->foreign('origin_region')->references('code')->on('pr_shipping_regions');
            $table->foreign('destination_region')->references('code')->on('pr_shipping_regions');
            $table->unique(['origin_region', 'destination_region', 'max_weight_kg', 'effective_from'], 'pr_shipping_rates_unique');
            $table->index(['destination_region', 'origin_region', 'effective_from'], 'pr_shipping_rates_lookup');
        });

        // Above the heaviest tier: fee at over_kg + ceil(kg - over_kg) × per_kg_fee.
        Schema::create('pr_shipping_overweight_rates', function (Blueprint $table) {
            $table->id();
            $table->string('origin_region', 10)->nullable();     // null: generic per-kg rate to the destination
            $table->string('destination_region', 10);
            $table->decimal('over_kg', 6, 2)->default(10);
            $table->decimal('per_kg_fee', 10, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->foreign('origin_region')->references('code')->on('pr_shipping_regions');
            $table->foreign('destination_region')->references('code')->on('pr_shipping_regions');
            $table->unique(['origin_region', 'destination_region', 'effective_from'], 'pr_shipping_overweight_unique');
            $table->index(['destination_region', 'origin_region', 'effective_from'], 'pr_shipping_overweight_lookup');
        });

        // How far a route is: level 0 (local) to 4 (remote islands), each with its own price list.
        Schema::create('pr_shipping_levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->primary();
            $table->string('name');
            $table->decimal('over_kg', 6, 2)->default(10);
            $table->decimal('per_kg_fee', 10, 2);
            $table->timestamps();
        });

        Schema::create('pr_shipping_level_fees', function (Blueprint $table) {
            $table->unsignedTinyInteger('level');
            $table->decimal('max_weight_kg', 6, 2);
            $table->decimal('fee', 10, 2);
            $table->primary(['level', 'max_weight_kg']);
            $table->foreign('level')->references('level')->on('pr_shipping_levels')->cascadeOnDelete();
        });

        // Which level each (seller region → buyer region) route is. Kept symmetric.
        Schema::create('pr_shipping_route_levels', function (Blueprint $table) {
            $table->string('origin_region', 10);
            $table->string('destination_region', 10);
            $table->unsignedTinyInteger('level');
            $table->timestamp('updated_at')->nullable();
            $table->primary(['origin_region', 'destination_region']);
            $table->foreign('origin_region')->references('code')->on('pr_shipping_regions');
            $table->foreign('destination_region')->references('code')->on('pr_shipping_regions');
            $table->foreign('level')->references('level')->on('pr_shipping_levels');
        });

        // STANDARD / HEAVY / BULKY by chargeable weight, and what the delivery rider earns.
        Schema::create('pr_parcel_classes', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->decimal('min_kg_exclusive', 6, 2);
            $table->decimal('max_kg_inclusive', 6, 2);
            $table->decimal('rider_pay', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The prices are reference data every environment needs, so they load with the schema.
        (new ShippingRatesSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_parcel_classes');
        Schema::dropIfExists('pr_shipping_route_levels');
        Schema::dropIfExists('pr_shipping_level_fees');
        Schema::dropIfExists('pr_shipping_levels');
        Schema::dropIfExists('pr_shipping_overweight_rates');
        Schema::dropIfExists('pr_shipping_rates');
        Schema::dropIfExists('pr_shipping_region_provinces');
        Schema::dropIfExists('pr_shipping_regions');
    }
};
