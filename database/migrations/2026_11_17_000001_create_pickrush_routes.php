<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A province hub sends one truck to several of its stations in one run. The route holds the
 * truck, driver and stop order; each stop keeps its own trip (manifest), checked by that station.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_no', 30)->unique();
            $table->foreignId('hub_id')->constrained('pr_hubs');
            $table->string('status', 20)->default('draft')->index();   // draft | dispatched | completed
            $table->foreignId('vehicle_id')->nullable()->constrained('pr_vehicles')->nullOnDelete();
            $table->string('vehicle_plate', 20)->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('pr_drivers')->nullOnDelete();
            $table->string('driver_name')->nullable();
            $table->foreignId('created_by')->constrained('pr_staff');
            $table->foreignId('dispatched_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['hub_id', 'status']);
        });
        Schema::table('pr_manifests', function (Blueprint $table) {
            $table->foreignId('route_id')->nullable()->after('to_hub_id')->constrained('pr_routes')->cascadeOnDelete();
            $table->unsignedTinyInteger('stop_order')->nullable()->after('route_id');
        });
    }

    public function down(): void
    {
        Schema::table('pr_manifests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_id');
            $table->dropColumn('stop_order');
        });
        Schema::dropIfExists('pr_routes');
    }
};
