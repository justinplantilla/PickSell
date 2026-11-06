<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout vouchers beyond a product's own code. A store voucher (seller_id set) takes money off
 * that store's items and the seller bears it: the orders are saved at the discounted amount, so
 * commission follows. A platform voucher (seller_id null) is PickSell's: orders keep their
 * amount, the parcel collects less, and the discount is recorded on the shipment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('title', 80);
            $table->string('type', 20);                              // fixed, percent, free_shipping (platform only)
            $table->decimal('amount', 10, 2)->default(0);           // pesos for fixed, % for percent
            $table->decimal('min_spend', 10, 2)->default(0);
            $table->decimal('max_discount', 10, 2)->nullable();     // cap for percent and free shipping
            $table->unsignedInteger('usage_limit')->nullable();     // across all buyers
            $table->unsignedInteger('per_buyer_limit')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['seller_id', 'code']);
            $table->index(['is_active', 'ends_at']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            $table->index(['voucher_id', 'buyer_id']);
        });

        Schema::table('order_shipments', function (Blueprint $table) {
            $table->foreignId('store_voucher_id')->nullable()->after('buyer_note')->constrained('vouchers')->nullOnDelete();
            $table->decimal('store_discount', 10, 2)->default(0)->after('store_voucher_id');
            $table->foreignId('platform_voucher_id')->nullable()->after('store_discount')->constrained('vouchers')->nullOnDelete();
            $table->decimal('platform_discount', 10, 2)->default(0)->after('platform_voucher_id');
            $table->decimal('shipping_discount', 10, 2)->default(0)->after('platform_discount');
        });

        Schema::table('orders', function (Blueprint $table) {
            // This line's share of the store voucher (already taken off amount).
            $table->decimal('store_discount', 10, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('store_discount'));
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_voucher_id');
            $table->dropConstrainedForeignId('platform_voucher_id');
            $table->dropColumn(['store_discount', 'platform_discount', 'shipping_discount']);
        });
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};
