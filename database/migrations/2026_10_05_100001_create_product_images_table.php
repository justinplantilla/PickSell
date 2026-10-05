<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('image_url');
            $table->unsignedInteger('display_order')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->string('alt_text')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'display_order']);
        });

        // Backfill: each legacy products.image becomes the primary product_images row.
        DB::table('products')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($products) {
                $now = now();
                DB::table('product_images')->insert($products->map(fn ($product) => [
                    'product_id'    => $product->id,
                    'image_url'     => $product->image,
                    'display_order' => 1,
                    'is_primary'    => true,
                    'alt_text'      => $product->name,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
