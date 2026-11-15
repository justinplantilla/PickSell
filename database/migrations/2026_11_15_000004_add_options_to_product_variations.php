<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A product can be sold in up to three options at once (e.g. Color and Size). Each row is then one
 * combination: options holds its [name, value] pairs in order, while type and value keep the
 * joined text ("Color / Size", "Red / M") that carts, orders and the buyer app already show.
 * Existing single-option rows become a one-pair combination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->json('options')->nullable()->after('value');
        });

        DB::table('product_variations')->whereNull('options')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('product_variations')->where('id', $row->id)
                    ->update(['options' => json_encode([[$row->type, $row->value]])]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
