<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A seller dropping off several parcels at once brings one manifest (DO… barcode). The station
 * scans it to see what to expect, scans each parcel, then confirms; the seller gets the receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_dropoff_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no', 20)->unique();
            $table->foreignId('origin_hub_id')->constrained('pr_hubs');
            $table->foreignId('seller_id')->constrained('users');
            $table->unsignedInteger('parcel_count');
            $table->string('status', 20)->default('open');   // open | received
            $table->foreignId('received_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['origin_hub_id', 'status']);
        });
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->foreignId('dropoff_batch_id')->nullable()->constrained('pr_dropoff_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dropoff_batch_id');
        });
        Schema::dropIfExists('pr_dropoff_batches');
    }
};
