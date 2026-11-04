<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What shipping is charged on: the packed weight of one unit. It stays null on existing
 * products until the seller enters it, and such a product cannot be checked out (its shipping
 * fee is unknown).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight_kg', 8, 2)->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('weight_kg');
        });
    }
};
