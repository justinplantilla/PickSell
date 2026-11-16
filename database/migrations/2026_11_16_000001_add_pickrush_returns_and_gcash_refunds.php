<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer returns ride a Pickrush return waybill (pickup at the buyer, hand-over to the seller, who
 * pays the return fee) and the refund is sent by PickSell through GCash out of the held payment:
 * the buyer's GCash account on the request, the fee and the seller's inspection on the return,
 * and the transfer reference on the refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->string('refund_gcash_number', 20)->nullable();
            $table->string('refund_gcash_name', 120)->nullable();
            $table->decimal('return_fee', 10, 2)->nullable();
            $table->string('inspection_result', 20)->nullable();   // passed | failed
            $table->text('inspection_note')->nullable();
            $table->timestamp('inspected_at')->nullable();
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('payout_method', 20)->nullable();
            $table->string('payout_reference', 120)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['payout_method', 'payout_reference']);
        });
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn(['refund_gcash_number', 'refund_gcash_name', 'return_fee', 'inspection_result', 'inspection_note', 'inspected_at']);
        });
    }
};
