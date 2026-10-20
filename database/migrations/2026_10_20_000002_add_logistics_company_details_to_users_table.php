<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics provider registration asks for the SEC registration number (companies), the region of the
 * company address and the regions the provider serves. Logistics applicants now confirm their email with
 * a one-time code after submitting, so applicants registered before this are treated as already verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sec_registration_no', 11)->nullable()->unique()->after('tin');
            $table->string('region', 100)->nullable()->after('house_no');
            $table->json('service_areas')->nullable()->after('sec_registration_no');
        });

        DB::table('users')->where('role', 'logistics')->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['sec_registration_no']);
            $table->dropColumn(['sec_registration_no', 'region', 'service_areas']);
        });
    }
};
