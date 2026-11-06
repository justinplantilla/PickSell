<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The option the buyer chose (e.g. "Size: XL"), so the seller knows what to pack. The label is
 * copied onto the order so it survives the seller later renaming or deleting the option.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('variation_id')->nullable()->after('product_id')->constrained('product_variations')->nullOnDelete();
            $table->string('variation_label')->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variation_id');
            $table->dropColumn('variation_label');
        });
    }
};
