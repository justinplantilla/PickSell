<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rider batches and notifications. Pickups the dispatcher gives one rider in one go become a batch
 * the rider accepts or declines in the rider app; the rider is notified in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_rider_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no', 20)->nullable()->unique();     // PK-0001, set once the id is known
            $table->enum('type', ['pickup'])->default('pickup');
            $table->foreignId('rider_id')->constrained('pr_riders');
            $table->foreignId('station_hub_id')->constrained('pr_hubs');
            $table->enum('status', ['offered', 'accepted', 'declined', 'completed', 'cancelled'])->default('offered');
            $table->foreignId('assigned_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('offered_at');
            $table->timestamp('responded_at')->nullable();             // accepted or declined
            $table->string('decline_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['rider_id', 'status']);
        });

        Schema::table('pr_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('rider_id')->index();
        });

        Schema::create('pr_rider_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders')->cascadeOnDelete();
            $table->string('type', 40);                                // pickup_offered, pickup_removed, ...
            $table->string('title');
            $table->string('body')->nullable();
            $table->json('data')->nullable();                          // e.g. {"batch_id": 12}
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['rider_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_rider_notifications');
        Schema::table('pr_assignments', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
        Schema::dropIfExists('pr_rider_batches');
    }
};
