<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete()->after('order_number');
            $table->integer('quantity')->default(1)->after('product_name');
            $table->string('waybill_number')->nullable()->after('status');
            $table->string('tracking_status')->nullable()->after('waybill_number');
            $table->timestamp('packed_at')->nullable()->after('tracking_status');
            $table->timestamp('handed_over_at')->nullable()->after('packed_at');
            $table->timestamp('delivered_at')->nullable()->after('handed_over_at');
            $table->tinyInteger('rating')->nullable()->after('delivered_at');
            $table->text('feedback')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id','quantity','waybill_number','tracking_status','packed_at','handed_over_at','delivered_at','rating','feedback']);
        });
    }
};
