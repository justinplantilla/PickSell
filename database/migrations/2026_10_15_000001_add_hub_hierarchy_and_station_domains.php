<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_branches', function (Blueprint $table) {
            $table->string('hub_type', 24)->default('station_hub')->after('logistics_id');
            $table->foreignId('parent_id')->nullable()->after('hub_type')
                ->constrained('logistics_branches')->nullOnDelete();
            $table->string('subdomain', 63)->nullable()->unique()->after('parent_id');
            $table->index(['hub_type', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('assigned_hub_id')->nullable()->after('role')
                ->constrained('logistics_branches')->nullOnDelete()->unique();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('origin_hub_id')->nullable()->after('origin_branch_id')
                ->constrained('logistics_branches')->nullOnDelete();
            $table->foreignId('destination_hub_id')->nullable()->after('destination_branch_id')
                ->constrained('logistics_branches')->nullOnDelete();
            $table->timestamp('linehaul_dispatched_at')->nullable()->after('destination_hub_id');
            $table->timestamp('destination_hub_received_at')->nullable()->after('linehaul_dispatched_at');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('origin_hub_id')->nullable()->after('origin_branch_id')
                ->constrained('logistics_branches')->nullOnDelete();
            $table->foreignId('destination_hub_id')->nullable()->after('destination_branch_id')
                ->constrained('logistics_branches')->nullOnDelete();
            $table->timestamp('linehaul_dispatched_at')->nullable()->after('destination_hub_id');
            $table->timestamp('destination_hub_received_at')->nullable()->after('linehaul_dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['linehaul_dispatched_at', 'destination_hub_received_at']);
            $table->dropConstrainedForeignId('origin_hub_id');
            $table->dropConstrainedForeignId('destination_hub_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['linehaul_dispatched_at', 'destination_hub_received_at']);
            $table->dropConstrainedForeignId('origin_hub_id');
            $table->dropConstrainedForeignId('destination_hub_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_hub_id');
        });

        Schema::table('logistics_branches', function (Blueprint $table) {
            $table->dropIndex(['hub_type', 'status']);
            $table->dropUnique(['subdomain']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['hub_type', 'subdomain']);
        });
    }
};
