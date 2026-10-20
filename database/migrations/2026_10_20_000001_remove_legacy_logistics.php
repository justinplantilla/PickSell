<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the first logistics implementation (courier and logistics user roles, branches, rider
 * assignments, parcel scans, logistics exceptions). Delivery now belongs to Pickrush, which keeps
 * its own pr_* tables and accounts. Not reversible: restore from a backup to go back.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['parcel_scans', 'logistics_exceptions', 'rider_barangays', 'branch_riders'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');
            $table->dropConstrainedForeignId('logistics_id');
            $table->dropConstrainedForeignId('origin_branch_id');
            $table->dropConstrainedForeignId('destination_branch_id');
            $table->dropConstrainedForeignId('destination_barangay_id');
            $table->dropColumn('assigned_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropColumn(['provider_type', 'vehicle_type', 'plate_number', 'or_cr_upload', 'delivery_area']);
        });

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
};
