<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An option can show one of its product's gallery images (e.g. Color: Red shows the red photo).
 * Deleting that image from the gallery only clears the link; the option stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->foreignId('image_id')->nullable()->after('stock')->constrained('product_images')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_id');
        });
    }
};
