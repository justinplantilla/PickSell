<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How often each product is viewed and added to carts, per day, for the seller's product analytics. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('cart_adds')->default(0);
            $table->unique(['product_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_daily_stats');
    }
};
