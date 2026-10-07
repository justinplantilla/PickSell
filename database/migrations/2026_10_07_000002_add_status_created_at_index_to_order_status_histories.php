<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->index(['to_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropIndex(['to_status', 'created_at']);
        });
    }
};
