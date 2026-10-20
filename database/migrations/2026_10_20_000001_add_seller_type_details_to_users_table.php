<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller registration asks for the seller type (individual, sole proprietorship or corporation), the
 * TIN, the pickup postal code and the business documents each seller type must provide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('seller_type', 30)->nullable()->after('line_of_business');
            $table->string('tin', 15)->nullable()->unique()->after('seller_type');
            $table->string('dti_certificate')->nullable()->after('business_permit');
            $table->string('sec_certificate')->nullable()->after('dti_certificate');
            $table->string('bir_certificate')->nullable()->after('sec_certificate');
            $table->string('postal_code', 4)->nullable()->after('house_no');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tin']);
            $table->dropColumn(['seller_type', 'tin', 'dti_certificate', 'sec_certificate', 'bir_certificate', 'postal_code']);
        });
    }
};
