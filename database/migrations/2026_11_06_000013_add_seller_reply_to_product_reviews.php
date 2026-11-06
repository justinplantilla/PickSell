<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The seller's public answer to a product review. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->text('seller_reply')->nullable()->after('body');
            $table->timestamp('seller_replied_at')->nullable()->after('seller_reply');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['seller_reply', 'seller_replied_at']);
        });
    }
};
