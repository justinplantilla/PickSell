<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An option can cost more or less than the product (e.g. XL ₱350 while the shirt is ₱300). Empty
 * means the product's price. The product's discount and voucher apply to it the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
