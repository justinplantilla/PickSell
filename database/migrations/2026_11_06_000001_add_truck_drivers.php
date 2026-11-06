<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registered truck drivers. Pickrush hires the drivers; the trucks are company-owned and kept
 * per hub. A driver belongs to the hub that dispatches them, each truck has a default driver,
 * and a trip records the truck and driver it left with (plate and name stay on the trip as the
 * printed snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained('pr_hubs');
            $table->string('full_name');
            $table->string('mobile_no', 20)->unique();
            $table->string('license_no', 30)->unique();
            $table->string('license_codes', 60);                 // LTO restriction codes, e.g. "B,B2,C"
            $table->date('license_expiry');
            $table->string('license_front_path')->nullable();    // private disk
            $table->string('license_back_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['hub_id', 'is_active']);
        });

        Schema::table('pr_vehicles', function (Blueprint $table) {
            $table->string('registered_owner')->nullable()->after('vehicle_type');
            $table->foreignId('default_driver_id')->nullable()->after('capacity_parcels')->constrained('pr_drivers')->nullOnDelete();
            $table->dropColumn(['driver_name', 'driver_mobile']);
        });

        Schema::table('pr_manifests', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('status')->constrained('pr_vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->after('vehicle_plate')->constrained('pr_drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pr_manifests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropConstrainedForeignId('vehicle_id');
        });

        Schema::table('pr_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_driver_id');
            $table->dropColumn('registered_owner');
            $table->string('driver_name')->nullable();
            $table->string('driver_mobile', 20)->nullable();
        });

        Schema::dropIfExists('pr_drivers');
    }
};
