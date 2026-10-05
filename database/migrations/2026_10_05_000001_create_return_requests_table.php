<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 60);
            $table->text('details');
            $table->string('status', 32)->default('requested')->index();
            $table->text('seller_note')->nullable();
            $table->string('dispute_status', 24)->default('not_open')->index();
            $table->text('admin_notes')->nullable();
            $table->string('admin_decision', 32)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refund_due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('admin_resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};