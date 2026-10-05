<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->constrained('users');
            $table->string('decision', 30);
            $table->text('reason')->nullable();
            $table->json('verification_snapshot')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['user_id', 'decision']);
            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_reviews');
    }
};
