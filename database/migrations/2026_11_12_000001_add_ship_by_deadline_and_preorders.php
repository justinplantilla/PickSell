<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sellers must hand a parcel to the courier within a time limit once they start preparing it
 * (longer for a pre-order); a late order is cancelled automatically and counts against the seller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Null: in stock and ships within the normal limit. Otherwise: days the seller needs.
            $table->unsignedTinyInteger('preorder_days')->nullable()->after('stock');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ship_by')->nullable()->after('preparing_at')->index();
            $table->timestamp('ship_reminded_at')->nullable()->after('ship_by');
            $table->timestamp('late_cancelled_at')->nullable()->after('ship_reminded_at');
            // What the product's own discount, sale or product voucher took off the list price (store vouchers: store_discount).
            $table->decimal('product_discount', 12, 2)->default(0)->after('store_discount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ship_by']);
            $table->dropColumn(['ship_by', 'ship_reminded_at', 'late_cancelled_at', 'product_discount']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('preorder_days'));
    }
};
