<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_key', 100)->nullable();
            $table->string('checkout_hash', 64)->nullable();
            $table->unique(['buyer_id', 'seller_id', 'checkout_key'], 'orders_checkout_store_unique');
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->string('product_name');
            $table->string('variation_label')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 12, 2);
            $table->decimal('store_discount', 12, 2)->default(0);
            $table->decimal('product_discount', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::table('return_requests', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
        });
        // Financial transactions are seller accounting entries. This is the buyer's payment,
        // linked to the existing rider collection/remittance audit trail when collected.
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('method')->default('cod');
            $table->string('status')->default('unpaid');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_collected', 12, 2)->default(0);
            $table->foreignId('assignment_id')->nullable()->constrained('pr_assignments')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
        });
        Schema::dropIfExists('order_items');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_checkout_store_unique');
            $table->dropColumn(['checkout_key', 'checkout_hash']);
        });
    }
};
