<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->json('attachments')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_status', 32)->default('not_started')->index();
            $table->timestamp('tracking_updated_at')->nullable();
        });

        Schema::create('return_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['return_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_events');
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn([
                'quantity',
                'refund_amount',
                'attachments',
                'carrier',
                'tracking_number',
                'tracking_status',
                'tracking_updated_at',
            ]);
        });
    }
};