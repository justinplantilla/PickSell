<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tracking_number')->unique();
            $table->string('status', 32)->default('placed');
            $table->foreignId('origin_branch_id')->nullable()->constrained('logistics_branches')->nullOnDelete();
            $table->foreignId('destination_branch_id')->nullable()->constrained('logistics_branches')->nullOnDelete();
            $table->foreignId('destination_barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->unsignedTinyInteger('delivery_attempts')->default(0);
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('sorting_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('users');
            $table->string('status', 16)->default('offered');
            $table->timestamp('offered_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status', 'offered_at']);
            $table->index(['delivery_id', 'created_at']);
        });

        Schema::create('delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 16)->default('system');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->string('proof_image_url')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['delivery_id', 'created_at']);
        });

        // Orders remain the commerce source of truth; materialize the logistics-side records.
        DB::table('orders')
            ->orderBy('id')
            ->select([
                'id', 'order_number', 'waybill_number', 'status', 'courier_id',
                'origin_branch_id', 'destination_branch_id', 'destination_barangay_id',
                'picked_up_at', 'sorting_received_at', 'out_for_delivery_at', 'delivered_at', 'updated_at',
            ])
            ->chunk(500, function ($orders): void {
                foreach ($orders as $order) {
                    $deliveryId = DB::table('deliveries')->insertGetId([
                        'order_id' => $order->id,
                        'tracking_number' => 'PS-'.str_pad((string) $order->id, 12, '0', STR_PAD_LEFT),
                        'status' => $order->status,
                        'origin_branch_id' => $order->origin_branch_id,
                        'destination_branch_id' => $order->destination_branch_id,
                        'destination_barangay_id' => $order->destination_barangay_id,
                        'delivery_attempts' => $order->status === 'delivery_failed' ? 1 : 0,
                        'picked_up_at' => $order->picked_up_at,
                        'sorting_at' => $order->sorting_received_at,
                        'out_for_delivery_at' => $order->out_for_delivery_at,
                        'delivered_at' => $order->delivered_at,
                        'returned_at' => $order->status === 'returned' ? $order->updated_at : null,
                        'created_at' => $order->updated_at,
                        'updated_at' => $order->updated_at,
                    ]);

                    if ($order->courier_id && in_array($order->status, [
                        'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed',
                    ], true)) {
                        DB::table('delivery_assignments')->insert([
                            'delivery_id' => $deliveryId,
                            'rider_id' => $order->courier_id,
                            'status' => 'accepted',
                            'offered_at' => $order->updated_at,
                            'responded_at' => $order->updated_at,
                            'created_at' => $order->updated_at,
                            'updated_at' => $order->updated_at,
                        ]);
                    }
                }
            });

        if (Schema::hasTable('order_status_histories')) {
            DB::table('order_status_histories')
                ->join('deliveries', 'deliveries.order_id', '=', 'order_status_histories.order_id')
                ->orderBy('order_status_histories.id')
                ->select([
                    'deliveries.id as delivery_id',
                    'order_status_histories.changed_by',
                    'order_status_histories.source',
                    'order_status_histories.from_status',
                    'order_status_histories.to_status',
                    'order_status_histories.reason',
                    'order_status_histories.created_at',
                ])
                ->chunk(500, function ($histories): void {
                    foreach ($histories as $history) {
                        $role = $history->source === 'courier' ? 'rider' : $history->source;
                        DB::table('delivery_logs')->insert([
                            'delivery_id' => $history->delivery_id,
                            'actor_id' => $history->changed_by,
                            'actor_role' => in_array($role, ['buyer', 'seller', 'logistics', 'rider', 'admin', 'system'], true)
                                ? $role
                                : 'system',
                            'from_status' => $history->from_status,
                            'to_status' => $history->to_status,
                            'note' => $history->reason,
                            'created_at' => $history->created_at,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_logs');
        Schema::dropIfExists('delivery_assignments');
        Schema::dropIfExists('deliveries');
    }
};
