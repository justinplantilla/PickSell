<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CURRENT_STATUSES = [
        'placed',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'picked_up',
        'at_sorting_center',
        'sorted',
        'assigned_to_rider',
        'out_for_delivery',
        'delivered',
        'completed',
        'delivery_failed',
        'returned',
        'cancelled',
    ];

    private const LEGACY_STATUSES = ['pending', 'processing', 'shipped'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $statuses = "'" . implode("','", [...self::CURRENT_STATUSES, ...self::LEGACY_STATUSES]) . "'";
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM({$statuses}) NOT NULL DEFAULT 'placed'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('orders')->whereIn('status', self::LEGACY_STATUSES)->exists()) {
            throw new RuntimeException('Cannot remove legacy order statuses while orders still use them.');
        }

        $statuses = "'" . implode("','", self::CURRENT_STATUSES) . "'";
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM({$statuses}) NOT NULL DEFAULT 'placed'");
    }
};