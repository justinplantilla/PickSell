<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every vacation a seller takes, so the days used each month can be counted against
 * User::MAX_VACATION_DAYS_PER_MONTH. users.vacation_* still holds the current one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_vacations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');              // the return date the seller picked
            $table->timestamp('ended_at')->nullable(); // set when the seller came back early
            $table->timestamps();
            $table->index(['seller_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_vacations');
    }
};
