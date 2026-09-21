<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('province');
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->timestamps();
            $table->unique(['province', 'name']);
        });

        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->timestamps();
            $table->unique(['municipality_id', 'name']);
        });

        Schema::create('logistics_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logistics_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->unique(['municipality_id', 'name']);
        });

        Schema::create('branch_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('logistics_branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->unique(['branch_id', 'user_id']);
        });

        Schema::create('rider_barangays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['branch_rider_id', 'barangay_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_barangays');
        Schema::dropIfExists('branch_riders');
        Schema::dropIfExists('logistics_branches');
        Schema::dropIfExists('barangays');
        Schema::dropIfExists('municipalities');
    }
};