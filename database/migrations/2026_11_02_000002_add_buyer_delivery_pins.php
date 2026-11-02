<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer delivery pins. Buyers pin their door in Picksell (copied onto each parcel booked for them, and onto
 * undelivered parcels when they move it), so the rider app can navigate to the delivery stop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('pickup_lng');   // buyer's delivery pin
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
        });

        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('delivery_municipality_code');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
        });
    }

    public function down(): void
    {
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->dropColumn(['delivery_lat', 'delivery_lng']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['delivery_lat', 'delivery_lng']);
        });
    }
};
