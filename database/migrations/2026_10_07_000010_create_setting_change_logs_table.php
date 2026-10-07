<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_change_logs', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100);
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();
            $table->index(['setting_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_change_logs');
    }
};
