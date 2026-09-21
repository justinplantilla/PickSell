<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('municipality_id')->nullable()->after('municipality')->constrained()->nullOnDelete();
            $table->foreignId('barangay_id')->nullable()->after('barangay')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('origin_branch_id')->nullable()->after('logistics_id')->constrained('logistics_branches')->nullOnDelete();
            $table->foreignId('destination_branch_id')->nullable()->after('origin_branch_id')->constrained('logistics_branches')->nullOnDelete();
            $table->foreignId('destination_barangay_id')->nullable()->after('destination_branch_id')->constrained('barangays')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_branch_id');
            $table->dropConstrainedForeignId('destination_branch_id');
            $table->dropConstrainedForeignId('destination_barangay_id');
            $table->dropColumn('assigned_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropConstrainedForeignId('barangay_id');
        });
    }
};