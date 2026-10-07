<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable();
        });

        Schema::create('commission_rate_histories', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate', 5, 2);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();
            $table->index(['effective_from', 'effective_until']);
        });

        Schema::table('financial_ledger_entries', function (Blueprint $table) {
            $table->foreignId('refund_id')->nullable()->change();
        });

        DB::table('orders')->orderBy('id')->chunkById(500, function ($orders): void {
            foreach ($orders as $order) {
                $amount = (float) $order->amount;
                $rate = $amount > 0
                    ? round((float) $order->commission / $amount * 100, 2, PHP_ROUND_HALF_UP)
                    : (float) config('app.platform_commission_rate', 10);

                DB::table('orders')->where('id', $order->id)->update(['commission_rate' => $rate]);

                $gross = round($amount, 2);
                $commission = round((float) $order->commission, 2);
                $sellerNet = round($gross - $commission, 2);
                if ($order->status !== 'completed') {
                    continue;
                }

                $entries = [
                    ['order_gross', 'marketplace_receivable', 'debit', $gross],
                    ['commission', 'commission_revenue', 'credit', $commission],
                    ['seller_net', 'seller_payable', 'credit', $sellerNet],
                ];
                foreach ($entries as [$type, $account, $direction, $entryAmount]) {
                    DB::table('financial_ledger_entries')->insert([
                        'refund_id' => null,
                        'order_id' => $order->id,
                        'entry_type' => $type,
                        'account' => $account,
                        'direction' => $direction,
                        'amount' => $entryAmount,
                        'created_at' => $order->created_at,
                    ]);
                }
            }
        });

        $setting = DB::table('platform_settings')->where('key', 'commission_rate')->first();
        $effectiveFrom = $setting?->updated_at ?? now();
        DB::table('commission_rate_histories')->insert([
            'rate' => $setting?->value ?? config('app.platform_commission_rate', 10),
            'changed_by' => null,
            'effective_from' => $effectiveFrom,
            'effective_until' => null,
            'created_at' => $effectiveFrom,
            'updated_at' => $effectiveFrom,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rate_histories');

        DB::table('financial_ledger_entries')
            ->whereNull('refund_id')
            ->whereIn('entry_type', ['order_gross', 'commission', 'seller_net'])
            ->delete();

        Schema::table('financial_ledger_entries', function (Blueprint $table) {
            $table->foreignId('refund_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
