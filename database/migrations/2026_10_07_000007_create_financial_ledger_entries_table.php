<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 40);
            $table->string('account', 40);
            $table->string('direction', 8);
            $table->decimal('amount', 12, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['refund_id', 'entry_type']);
            $table->index(['order_id', 'created_at']);
        });

        foreach (DB::table('refunds')->whereIn('status', ['approved', 'processed'])->orderBy('id')->cursor() as $refund) {
            $amount = round((float) $refund->amount, 2);
            $reversal = round((float) $refund->commission_reversal, 2);
            $sellerAdjustment = round((float) $refund->seller_adjustment, 2);
            $entries = [
                ['refund_gross', 'refund_payable', 'debit', $amount],
                ['commission_reversal', 'commission_revenue', 'credit', $reversal],
                ['seller_adjustment', 'seller_payable', 'credit', $sellerAdjustment],
            ];

            foreach ($entries as [$type, $account, $direction, $entryAmount]) {
                DB::table('financial_ledger_entries')->insert([
                    'refund_id' => $refund->id,
                    'order_id' => $refund->order_id,
                    'entry_type' => $type,
                    'account' => $account,
                    'direction' => $direction,
                    'amount' => $entryAmount,
                    'created_at' => $refund->approved_at ?? $refund->created_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledger_entries');
    }
};
