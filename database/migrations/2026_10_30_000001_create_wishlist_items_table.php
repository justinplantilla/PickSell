<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products a buyer saved for later. Shared with the Picksell buyer mobile app, which reads and
 * writes the same rows, so a wishlist follows the account between the website and phones.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Some development copies already have this table from an early mobile-app build,
        // with the same columns; keep it and its rows.
        if (Schema::hasTable('wishlist_items')) {
            return;
        }

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
