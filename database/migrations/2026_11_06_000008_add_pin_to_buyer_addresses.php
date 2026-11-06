<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Address-book entries get their own door pin, like the default address, for the rider app. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_addresses', function (Blueprint $table) {
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('house_no');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_addresses', function (Blueprint $table) {
            $table->dropColumn(['delivery_lat', 'delivery_lng']);
        });
    }
};
