<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riders apply in the rider app (email and password, verified by an email code) and the station
 * hub manager approves. Applications carry no staff creator or uploader, so those become optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_riders', function (Blueprint $table) {
            $table->string('first_name', 60)->nullable()->after('zone_id');
            $table->string('last_name', 60)->nullable()->after('first_name');
            $table->string('email')->nullable()->unique()->after('mobile_no');    // the rider app login
            $table->string('password')->nullable()->after('email');
            // The address as picked in the app (PSGC codes and names), so a rejected rider can edit it.
            $table->string('province_code', 10)->nullable()->after('address');
            $table->string('province_name', 100)->nullable()->after('province_code');
            $table->string('city_code', 10)->nullable()->after('province_name');
            $table->string('city_name', 100)->nullable()->after('city_code');
            $table->string('barangay_code', 10)->nullable()->after('city_name');
            $table->string('barangay_name', 100)->nullable()->after('barangay_code');
            $table->string('street')->nullable()->after('barangay_name');
            $table->string('license_no', 30)->nullable()->after('plate_no');
            $table->timestamp('applied_at')->nullable()->after('status_reason');  // first submit or latest resubmit
            $table->unsignedBigInteger('created_by')->nullable()->change();        // null: applied in the rider app
        });

        Schema::table('pr_rider_documents', function (Blueprint $table) {
            $table->string('side', 10)->nullable()->after('doc_type');             // front / back for the license
            $table->unsignedBigInteger('uploaded_by')->nullable()->change();       // null: uploaded by the rider
        });

        Schema::create('pr_rider_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();                              // sha256 of the bearer token
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pr_rider_email_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();                  // handed to the app after a correct code
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_rider_email_codes');
        Schema::dropIfExists('pr_rider_tokens');

        Schema::table('pr_rider_documents', function (Blueprint $table) {
            $table->dropColumn('side');
        });

        Schema::table('pr_riders', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['first_name', 'last_name', 'email', 'password', 'province_code', 'province_name',
                'city_code', 'city_name', 'barangay_code', 'barangay_name', 'street', 'license_no', 'applied_at']);
        });
    }
};
