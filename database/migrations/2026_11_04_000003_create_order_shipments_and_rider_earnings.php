<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One seller's orders from one checkout ship as one parcel with one delivery fee. The shipment
 * keeps the price and its split exactly as quoted at checkout (later rate or commission changes
 * never touch it), the parcel carries the same numbers, and riders earn from a ledger that can
 * credit a parcel's pickup or delivery only once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();               // the parcel's external_ref (idempotency key)
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained('users');
            $table->string('origin_province')->nullable();             // the seller's, as saved at checkout
            $table->string('origin_region', 10)->nullable();             // null: the seller's province did not match a region
            $table->string('destination_region', 10);
            $table->string('price_source', 20);                          // ORIGIN_DESTINATION or GENERIC_FALLBACK
            $table->string('parcel_class', 20);
            $table->decimal('chargeable_weight_kg', 8, 2);
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('rider_pay', 10, 2);
            $table->decimal('platform_commission', 10, 2);            // PickSell's cut of the delivery fee
            $table->decimal('logistics_share', 10, 2);
            $table->decimal('delivery_commission_rate', 5, 2);
            $table->string('rates_version', 100);
            // pending → delivered; failed after an attempt (may still be delivered later); returned / cancelled void it.
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('earnings_posted_at')->nullable();
            $table->timestamps();
        });

        // Who earned what from a delivered parcel's fee; one row per party, so it is credited once.
        Schema::create('shipment_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_shipment_id')->constrained('order_shipments');
            $table->string('party', 20);                              // rider, logistics, picksell
            $table->decimal('amount', 10, 2);
            $table->timestamp('posted_at');
            $table->unique(['order_shipment_id', 'party']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('order_shipment_id')->nullable()->after('seller_id')->constrained('order_shipments')->nullOnDelete();
        });

        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->string('parcel_class', 20)->nullable()->after('shipping_fee');
            $table->string('origin_region', 10)->nullable()->after('parcel_class');
            $table->string('destination_region', 10)->nullable()->after('origin_region');
            $table->string('price_source', 20)->nullable()->after('destination_region');
            $table->decimal('rider_pay', 10, 2)->nullable()->after('destination_region');
            $table->decimal('platform_commission', 10, 2)->nullable()->after('rider_pay');
            $table->decimal('logistics_share', 10, 2)->nullable()->after('platform_commission');
        });

        Schema::create('pr_rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders');
            $table->foreignId('parcel_id')->constrained('pr_parcels');
            $table->foreignId('assignment_id')->nullable()->constrained('pr_assignments')->nullOnDelete();
            $table->string('kind', 20);                               // pickup, delivery, return
            $table->decimal('amount', 10, 2);
            $table->timestamp('earned_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['parcel_id', 'kind']);                    // a parcel's pickup or delivery pays once
            $table->index(['rider_id', 'earned_at']);
        });

        // Earnings used to be counted from finished tasks at flat rates (₱15 pickup, ₱35 delivery); keep that history.
        $rates = ['pickup' => 15, 'delivery' => 35];
        DB::table('pr_assignments')->where('status', 'completed')->whereIn('type', ['pickup', 'delivery', 'return'])
            ->orderBy('id')->get(['id', 'rider_id', 'parcel_id', 'type', 'completed_at', 'created_at'])
            ->each(fn ($task) => DB::table('pr_rider_earnings')->insertOrIgnore([
                'rider_id' => $task->rider_id, 'parcel_id' => $task->parcel_id, 'assignment_id' => $task->id,
                'kind' => $task->type, 'amount' => $rates[$task->type === 'pickup' ? 'pickup' : 'delivery'],
                'earned_at' => $task->completed_at ?? $task->created_at, 'created_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_rider_earnings');
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->dropColumn(['parcel_class', 'origin_region', 'destination_region', 'price_source', 'rider_pay', 'platform_commission', 'logistics_share']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_shipment_id');
        });
        Schema::dropIfExists('shipment_earnings');
        Schema::dropIfExists('order_shipments');
    }
};
