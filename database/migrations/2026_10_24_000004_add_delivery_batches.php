<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery batches in the rider app. A batch is now a pickup batch or a delivery batch (deliveries
 * and returns to sellers). The rider scans each parcel when loading at the station (loaded_at), and
 * a parcel they could not deliver is scanned back in at the station when they return (returned_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_rider_batches', function (Blueprint $table) {
            $table->string('type', 20)->default('pickup')->change();     // pickup, delivery
            $table->timestamp('started_at')->nullable()->after('responded_at');   // delivery: left the station
        });

        Schema::table('pr_assignments', function (Blueprint $table) {
            $table->timestamp('loaded_at')->nullable()->after('arrival_distance_m');
            $table->timestamp('returned_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('pr_assignments', function (Blueprint $table) {
            $table->dropColumn(['loaded_at', 'returned_at']);
        });
        Schema::table('pr_rider_batches', function (Blueprint $table) {
            $table->dropColumn('started_at');
            $table->enum('type', ['pickup'])->default('pickup')->change();
        });
    }
};
