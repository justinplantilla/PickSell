<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Besides the product (orders.rating), buyers rate the seller's service and the delivery. The
 * delivery rating is kept with the Pickrush rider who delivered the parcel (no foreign key: the
 * logistics tables belong to Pickrush and are reached through PicksellBridge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('seller_rating')->nullable()->after('rating');
            $table->unsignedTinyInteger('delivery_rating')->nullable()->after('seller_rating');
            $table->unsignedBigInteger('delivery_rider_id')->nullable()->index()->after('delivery_rating');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_rider_id']);
            $table->dropColumn(['seller_rating', 'delivery_rating', 'delivery_rider_id']);
        });
    }
};
