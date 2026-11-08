<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-registration of logistics companies on the portal: the business address, the applicant's
 * personal details (they become the company's first super admin), terms acceptance and the two
 * required documents. A company gets its tracking prefix when it is approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_companies', function (Blueprint $table) {
            $table->string('tracking_prefix', 4)->nullable()->change();
            $table->string('province_code', 9)->nullable()->after('is_default');
            $table->string('municipality_code', 9)->nullable()->after('province_code');
            $table->string('barangay_code', 9)->nullable()->after('municipality_code');
            $table->string('street_address')->nullable()->after('barangay_code');
            $table->unsignedBigInteger('owner_staff_id')->nullable()->after('street_address');
            $table->timestamp('submitted_at')->nullable()->after('owner_staff_id');
            $table->string('rejection_reason', 1000)->nullable()->after('approved_at');
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 20)->nullable();
        });

        Schema::table('pr_staff', function (Blueprint $table) {
            $table->string('first_name', 50)->nullable()->after('full_name');
            $table->string('middle_initial', 2)->nullable()->after('first_name');
            $table->string('last_name', 50)->nullable()->after('middle_initial');
            $table->enum('sex', ['male', 'female'])->nullable()->after('last_name');
            $table->date('birthday')->nullable()->after('sex');
            $table->string('position', 100)->nullable()->after('birthday');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        Schema::create('pr_company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('pr_companies')->cascadeOnDelete();
            $table->enum('type', ['valid_id', 'dti_permit']);
            $table->string('path');                              // private disk, never a public URL
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('version')->default(1); // a resubmission adds a new version
            $table->timestamps();
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_company_documents');
        Schema::table('pr_staff', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_initial', 'last_name', 'sex', 'birthday', 'position', 'email_verified_at']);
        });
        Schema::table('pr_companies', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'municipality_code', 'barangay_code', 'street_address', 'owner_staff_id',
                'submitted_at', 'rejection_reason', 'terms_accepted_at', 'terms_version']);
        });
    }
};
