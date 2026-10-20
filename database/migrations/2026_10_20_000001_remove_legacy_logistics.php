<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the first logistics implementation (courier and logistics user roles, branches, rider
 * assignments, parcel scans, logistics exceptions) and the uncommitted delivery-domain tables some
 * databases carry (deliveries, delivery_assignments, delivery_logs, hub columns on orders/users).
 * Delivery now belongs to Pickrush, which keeps its own pr_* tables and accounts.
 *
 * Every step checks first, so the migration also finishes a database where an earlier run stopped
 * partway (MySQL cannot roll back schema changes). Not reversible: restore from a backup to go back.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['delivery_logs', 'delivery_assignments', 'deliveries', 'parcel_scans', 'logistics_exceptions', 'rider_barangays', 'branch_riders'] as $table) {
            Schema::dropIfExists($table);
        }

        $this->dropColumns('orders', ['courier_id', 'logistics_id', 'origin_branch_id', 'destination_branch_id', 'destination_barangay_id', 'origin_hub_id', 'destination_hub_id', 'assigned_at']);
        $this->dropColumns('users', ['municipality_id', 'barangay_id', 'assigned_hub_id', 'provider_type', 'vehicle_type', 'plate_number', 'or_cr_upload', 'delivery_area']);

        Schema::dropIfExists('logistics_branches');
        Schema::dropIfExists('barangays');
        Schema::dropIfExists('municipalities');

        // Old courier and logistics accounts: clear the audit rows that would block the delete.
        $legacyUserIds = DB::table('users')->whereIn('role', ['courier', 'logistics'])->pluck('id');
        if ($legacyUserIds->isNotEmpty()) {
            DB::table('user_status_histories')->whereIn('changed_by', $legacyUserIds)->orWhereIn('user_id', $legacyUserIds)->delete();
            DB::table('registration_reviews')->whereIn('reviewed_by', $legacyUserIds)->orWhereIn('user_id', $legacyUserIds)->delete();
            DB::table('users')->whereIn('id', $legacyUserIds)->delete();
        }
        DB::table('announcements')->where('audience', 'courier')->update(['audience' => 'all']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('buyer', 'seller', 'admin') NOT NULL DEFAULT 'buyer'");
            DB::statement("ALTER TABLE announcements MODIFY audience ENUM('all', 'buyer', 'seller') NOT NULL DEFAULT 'all'");
        }
    }

    public function down(): void
    {
        // Intentionally empty: the legacy logistics data is not recreated.
    }

    /** Drop whichever of these columns exist, with any foreign keys on them. */
    private function dropColumns(string $table, array $columns): void
    {
        $existing = array_values(array_intersect($columns, Schema::getColumnListing($table)));
        if ($existing === []) {
            return;
        }
        $foreignKeys = collect(Schema::getForeignKeys($table))
            ->filter(fn ($fk) => array_intersect($fk['columns'], $existing) !== []);
        // MySQL drops by the real constraint name; SQLite only by column (it rebuilds the table).
        $byName = DB::getDriverName() !== 'sqlite';

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKeys, $existing, $byName) {
            foreach ($foreignKeys as $fk) {
                $blueprint->dropForeign($byName ? $fk['name'] : $fk['columns']);
            }
            $blueprint->dropColumn($existing);
        });
    }
};
