<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = [
        'placed',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'picked_up',
        'at_sorting_center',
        'sorted',
        'in_transit',
        'assigned_to_rider',
        'out_for_delivery',
        'delivered',
        'completed',
        'delivery_failed',
        'returned',
        'cancelled',
        'pending',
        'processing',
        'shipped',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->setOrderStatuses(self::STATUSES);
        }

        // Databases that ran an earlier version of the hub hierarchy migration never got this column.
        if (! Schema::hasColumn('users', 'assigned_hub_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('assigned_hub_id')->nullable()->after('role')
                    ->constrained('logistics_branches')->nullOnDelete();
            });
        }

        // One operator account per hub; the foreign key added in the hub hierarchy migration was not unique.
        Schema::table('users', function (Blueprint $table) {
            $table->unique('assigned_hub_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['assigned_hub_id']);
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('orders')->where('status', 'in_transit')->exists()) {
            throw new RuntimeException('Cannot remove the in_transit order status while orders still use it.');
        }

        $this->setOrderStatuses(array_values(array_diff(self::STATUSES, ['in_transit'])));
    }

    private function setOrderStatuses(array $statuses): void
    {
        $list = "'".implode("','", $statuses)."'";
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM({$list}) NOT NULL DEFAULT 'placed'");
    }
};
