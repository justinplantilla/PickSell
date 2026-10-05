<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lifecycle timestamp columns from the spec; only those that do not already exist are added. */
    private const COLUMNS = [
        'confirmed_at', 'preparing_at', 'ready_for_pickup_at', 'picked_up_at', 'sorting_received_at',
        'sorted_at', 'assigned_to_rider_at', 'out_for_delivery_at', 'delivered_at', 'completed_at',
    ];

    /** Legacy columns that already captured the same moment: new column ← legacy column. */
    private const BACKFILL = [
        'preparing_at' => 'packed_at',             // set by SellerController::packOrder (→ preparing)
        'ready_for_pickup_at' => 'handed_over_at', // set by SellerController::handoverOrder (→ ready_for_pickup)
        'assigned_to_rider_at' => 'assigned_at',   // set by LogisticsController::assignCourier (→ assigned_to_rider)
        'completed_at' => 'confirmed_by_seller_at', // set by SellerController::confirmDelivery (→ completed)
    ];

    public function up(): void
    {
        $missing = array_values(array_filter(self::COLUMNS, fn ($column) => ! Schema::hasColumn('orders', $column)));

        Schema::table('orders', function (Blueprint $table) use ($missing) {
            foreach ($missing as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->index(['status', 'created_at']);
        });

        foreach (self::BACKFILL as $column => $legacy) {
            if (in_array($column, $missing, true) && Schema::hasColumn('orders', $legacy)) {
                DB::table('orders')->whereNull($column)->whereNotNull($legacy)->update([$column => DB::raw($legacy)]);
            }
        }

        // Every lifecycle change, whoever made it (seller, logistics, courier, buyer, admin, system).
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            // delivered_at predates this migration and is kept.
            $table->dropColumn(array_values(array_filter(self::COLUMNS, fn ($column) => $column !== 'delivered_at')));
        });
    }
};
