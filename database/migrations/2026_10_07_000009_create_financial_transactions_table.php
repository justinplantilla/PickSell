<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status', 30)->default('posted');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['seller_id', 'created_at']);
        });

        DB::table('financial_ledger_entries')
            ->leftJoin('orders', 'orders.id', '=', 'financial_ledger_entries.order_id')
            ->select('financial_ledger_entries.*', 'orders.seller_id')
            ->orderBy('financial_ledger_entries.id')
            ->chunk(500, function ($entries): void {
                foreach ($entries as $entry) {
                    $isRefund = $entry->refund_id !== null;
                    $createdAt = $entry->created_at;
                    DB::table('financial_transactions')->insert([
                        'order_id' => $entry->order_id,
                        'seller_id' => $entry->seller_id,
                        'type' => $entry->entry_type,
                        'debit' => $entry->direction === 'debit' ? $entry->amount : 0,
                        'credit' => $entry->direction === 'credit' ? $entry->amount : 0,
                        'amount' => $entry->amount,
                        'reference_type' => $isRefund ? 'refund' : 'order',
                        'reference_id' => $isRefund ? $entry->refund_id : $entry->order_id,
                        'status' => 'posted',
                        'description' => ucfirst(str_replace('_', ' ', $entry->entry_type)),
                        'created_by' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
