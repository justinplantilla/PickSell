<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trips no longer carry a seal number. The receiving hub still records when the truck arrived
 * (by the "Truck arrived" button or its first scan), which is what the overdue alert watches.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('logistics_issues')->where('type', 'seal_broken')->delete();
        DB::table('pr_exceptions')->where('type', 'seal_broken')->delete();
        Schema::table('pr_exceptions', function (Blueprint $table) {
            $table->enum('type', ['missing', 'damaged', 'mis_sorted', 'unlabeled', 'no_route'])->change();
        });

        Schema::table('pr_manifests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seal_approved_by');
            $table->dropUnique(['seal_no']);
            $table->dropColumn(['seal_no', 'seal_status', 'seal_found_no', 'seal_approved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pr_manifests', function (Blueprint $table) {
            $table->string('seal_no', 30)->nullable()->unique()->after('driver_name');
            $table->string('seal_status', 20)->nullable()->after('arrived_by');
            $table->string('seal_found_no', 30)->nullable()->after('seal_status');
            $table->foreignId('seal_approved_by')->nullable()->after('seal_found_no')->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('seal_approved_at')->nullable()->after('seal_approved_by');
        });

        Schema::table('pr_exceptions', function (Blueprint $table) {
            $table->enum('type', ['missing', 'damaged', 'mis_sorted', 'unlabeled', 'no_route', 'seal_broken'])->change();
        });
    }
};
