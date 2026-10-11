<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Merge any existing duplicate rows before adding the constraint
        $duplicates = DB::table('cart_items')
            ->select('cart_id', 'product_id', 'variation_id', DB::raw('MIN(id) as keep_id'), DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('cart_id', 'product_id', 'variation_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('cart_items')->where('id', $dup->keep_id)->update(['quantity' => $dup->total_qty]);
            DB::table('cart_items')
                ->where('cart_id', $dup->cart_id)
                ->where('product_id', $dup->product_id)
                ->where('variation_id', $dup->variation_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id', 'variation_id'], 'cart_items_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique');
        });
    }
};
