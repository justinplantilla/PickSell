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
        $this->setStatuses([...self::CURRENT_STATUSES, ...self::LEGACY_STATUSES]);
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        if (DB::table('orders')->whereIn('status', self::LEGACY_STATUSES)->exists()) {
            throw new RuntimeException('Cannot remove legacy order statuses while orders still use them.');
        }

        $this->setStatuses(self::CURRENT_STATUSES);
    }

    private function setStatuses(array $statuses): void
    {
        $list = "'" . implode("','", $statuses) . "'";

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM({$list}) NOT NULL DEFAULT 'placed'"),
            'pgsql' => DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check, ADD CONSTRAINT orders_status_check CHECK (status IN ({$list}))"),
            default => null,
        };
    }
};