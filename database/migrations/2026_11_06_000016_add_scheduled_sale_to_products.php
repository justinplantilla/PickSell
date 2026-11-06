<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sale a seller schedules ahead: between sale_starts_at and sale_ends_at the product's discount
 * is sale_discount instead of its regular one; outside the window it goes back by itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_discount', 5, 2)->nullable()->after('discount');
            $table->timestamp('sale_starts_at')->nullable()->after('sale_discount');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_discount', 'sale_starts_at', 'sale_ends_at']);
        });
    }
};
