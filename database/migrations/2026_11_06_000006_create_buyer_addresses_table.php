<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra delivery addresses a buyer can choose at checkout. The address on the buyer's profile
 * stays their default (and keeps the door pin); these are the others, e.g. work or a relative's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable();
            $table->string('recipient_name', 120);
            $table->string('contact_no', 20);
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('municipality_code', 10)->nullable();
            $table->string('barangay_code', 10)->nullable();
            $table->string('street')->nullable();
            $table->string('house_no', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_addresses');
    }
};
