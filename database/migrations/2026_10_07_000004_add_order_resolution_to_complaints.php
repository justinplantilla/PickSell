<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('resolution_type', 40)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'status']);
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['resolution_type', 'resolution_notes', 'resolved_at']);
        });
    }
};
