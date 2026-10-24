<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Map pins and rider arrivals. Sellers pin their shop in Picksell (copied onto each parcel they
 * book), hub managers pin their station, and the rider app records where the rider was when they
 * said "Arrived" (refused when a pin exists and they are too far). Riders can also report a
 * problem to their station.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('pickup_lat', 10, 7)->nullable()->after('house_no');     // seller's shop pin
            $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
        });

        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->decimal('pickup_lat', 10, 7)->nullable()->after('pickup_municipality_code');
            $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
        });

        Schema::table('pr_assignments', function (Blueprint $table) {
            $table->timestamp('arrived_at')->nullable()->after('assigned_at');
            $table->decimal('arrival_lat', 10, 7)->nullable()->after('arrived_at');
            $table->decimal('arrival_lng', 10, 7)->nullable()->after('arrival_lat');
            $table->unsignedInteger('arrival_distance_m')->nullable()->after('arrival_lng');  // null: no pin to compare
        });

        Schema::table('pr_rider_batches', function (Blueprint $table) {
            $table->timestamp('hub_arrived_at')->nullable()->after('responded_at');
            $table->decimal('hub_arrival_lat', 10, 7)->nullable()->after('hub_arrived_at');
            $table->decimal('hub_arrival_lng', 10, 7)->nullable()->after('hub_arrival_lat');
            $table->unsignedInteger('hub_arrival_distance_m')->nullable()->after('hub_arrival_lng');
        });

        Schema::create('pr_rider_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders');
            $table->foreignId('station_hub_id')->constrained('pr_hubs');
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->string('type', 30);                     // config pickrush.rider_problems
            $table->text('note')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['station_hub_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_rider_reports');
        Schema::table('pr_rider_batches', function (Blueprint $table) {
            $table->dropColumn(['hub_arrived_at', 'hub_arrival_lat', 'hub_arrival_lng', 'hub_arrival_distance_m']);
        });
        Schema::table('pr_assignments', function (Blueprint $table) {
            $table->dropColumn(['arrived_at', 'arrival_lat', 'arrival_lng', 'arrival_distance_m']);
        });
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->dropColumn(['pickup_lat', 'pickup_lng']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pickup_lat', 'pickup_lng']);
        });
    }
};
