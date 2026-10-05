<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'image')) {
            return;
        }

        // Integrity check: refuse to drop the legacy column if any image was not carried over.
        $missing = DB::table('products')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_images')
                    ->whereColumn('product_images.product_id', 'products.id')
                    ->whereColumn('product_images.image_url', 'products.image')
                    ->where('product_images.is_primary', true);
            })
            ->count();

        if ($missing > 0) {
            throw new RuntimeException("Aborting: {$missing} product image(s) were not migrated to product_images.");
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'image')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('image')->nullable()->after('stock');
            });
        }

        DB::table('product_images')
            ->where('is_primary', true)
            ->orderBy('id')
            ->each(function ($image) {
                DB::table('products')->where('id', $image->product_id)->update(['image' => $image->image_url]);
            });
    }
};
