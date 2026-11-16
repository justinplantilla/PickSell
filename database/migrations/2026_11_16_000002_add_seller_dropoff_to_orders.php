<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller drop-off: instead of waiting for a pickup rider the seller brings the parcel to their
 * station. The order remembers the choice and the deadline (2 days); past it the order is cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment', 10)->default('pickup');   // pickup | dropoff
            $table->timestamp('dropoff_by')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['dropoff_by']);
            $table->dropColumn(['fulfillment', 'dropoff_by']);
        });
    }
};
