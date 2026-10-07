<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('commission_reversal', 12, 2)->default(0);
            $table->decimal('seller_adjustment', 12, 2)->default(0);
            $table->string('status', 30)->default('requested');
            $table->text('reason')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('order_id');
        });

        if (! Schema::hasTable('return_requests')) {
            return;
        }

        foreach (DB::table('return_requests')
            ->whereIn('status', ['refund_due', 'completed'])
            ->whereNotNull('refund_amount')
            ->orderBy('id')
            ->cursor() as $request) {
            $amount = round((float) $request->refund_amount, 2);
            if ($amount <= 0) {
                continue;
            }

            $order = DB::table('orders')->where('id', $request->order_id)->first();
            if (! $order || (float) $order->amount <= 0) {
                continue;
            }

            $reversal = min(
                $amount,
                round($amount * (float) $order->commission / (float) $order->amount, 2, PHP_ROUND_HALF_UP),
            );
            DB::table('refunds')->insert([
                'order_id' => $request->order_id,
                'return_request_id' => $request->id,
                'requested_by' => $request->buyer_id,
                'approved_by' => $request->resolved_by,
                'amount' => $amount,
                'commission_reversal' => $reversal,
                'seller_adjustment' => round($amount - $reversal, 2),
                'status' => $request->status === 'completed' ? 'processed' : 'approved',
                'reason' => $request->admin_notes ?: 'Imported from the existing return refund workflow.',
                'approved_at' => $request->refund_due_at ?? $request->updated_at,
                'processed_at' => $request->completed_at,
                'created_at' => $request->created_at,
                'updated_at' => $request->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
