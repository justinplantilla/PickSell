<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Province hub operations: the sort scan (which bin a parcel went into), exceptions with a
 * resolution workflow, and the fleet used for trips.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_parcels', function (Blueprint $table) {
            // Set by the sort scan during the parcel's current stay at a hub; cleared when it moves on.
            $table->timestamp('sorted_at')->nullable()->after('next_hub_id');
            $table->foreignId('sorted_by')->nullable()->after('sorted_at')->constrained('pr_staff')->nullOnDelete();
        });

        Schema::create('pr_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained('pr_hubs');                 // where it was found
            $table->foreignId('against_hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete(); // who is answerable
            $table->foreignId('parcel_id')->nullable()->constrained('pr_parcels');
            $table->foreignId('manifest_id')->nullable()->constrained('pr_manifests')->nullOnDelete();
            $table->enum('type', ['missing', 'damaged', 'mis_sorted', 'unlabeled', 'no_route']);
            $table->text('note')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('status', ['open', 'resolved'])->default('open')->index();
            $table->string('resolution', 30)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['hub_id', 'status']);
            $table->index(['parcel_id', 'status']);
        });

        Schema::create('pr_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained('pr_hubs');
            $table->string('plate_no', 20)->unique();
            $table->string('vehicle_type', 30);
            $table->unsignedInteger('capacity_parcels')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_mobile', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_vehicles');
        Schema::dropIfExists('pr_exceptions');
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sorted_by');
            $table->dropColumn('sorted_at');
        });
    }
};
