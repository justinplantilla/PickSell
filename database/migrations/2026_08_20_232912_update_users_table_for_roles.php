<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');

            $table->enum('role', ['buyer', 'seller', 'courier', 'admin'])->default('buyer')->after('id');
            $table->enum('status', ['pending', 'approved', 'disapproved', 'suspended'])->default('pending')->after('role');

            // Personal info
            $table->string('last_name')->after('status');
            $table->string('first_name')->after('last_name');
            $table->string('middle_initial')->nullable()->after('first_name');
            $table->enum('sex', ['Male', 'Female'])->after('middle_initial');
            $table->string('contact_no')->after('sex');
            $table->date('birthday')->after('contact_no');
            $table->integer('age')->after('birthday');

            // Address
            $table->string('province')->after('age');
            $table->string('municipality')->after('province');
            $table->string('barangay')->after('municipality');
            $table->string('street')->nullable()->after('barangay');
            $table->string('house_no')->nullable()->after('street');

            // Upload ID
            $table->string('id_upload')->nullable()->after('house_no');

            // Seller fields
            $table->string('business_name')->nullable()->after('id_upload');
            $table->string('line_of_business')->nullable()->after('business_name');
            $table->string('business_permit')->nullable()->after('line_of_business');

            // Courier fields
            $table->string('vehicle_type')->nullable()->after('business_permit');
            $table->string('plate_number')->nullable()->after('vehicle_type');
            $table->string('or_cr_upload')->nullable()->after('plate_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->dropColumn([
                'role','status','last_name','first_name','middle_initial','sex',
                'contact_no','birthday','age','province','municipality','barangay',
                'street','house_no','id_upload','business_name','line_of_business',
                'business_permit','vehicle_type','plate_number','or_cr_upload',
            ]);
        });
    }
};
