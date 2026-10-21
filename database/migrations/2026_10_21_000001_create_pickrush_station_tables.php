<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Station hub operations: riders and their documents, delivery zones, trip manifests,
 * rider assignments, and the cash chain (rider → station → company). Every parcel is
 * cash on delivery, so the parcel's payment type column goes away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });

        Schema::create('pr_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_hub_id')->constrained('pr_hubs');
            $table->unsignedBigInteger('zone_id')->nullable()->index();
            $table->string('full_name');
            $table->string('mobile_no', 20)->unique();           // the rider app login
            $table->date('birthdate')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_no', 20)->nullable();
            $table->string('vehicle_type', 30)->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('plate_no', 20)->nullable();
            $table->enum('status', ['pending', 'under_review', 'active', 'suspended', 'deactivated', 'rejected'])->default('pending')->index();
            $table->enum('shift_status', ['on_shift', 'off_duty'])->default('off_duty');
            $table->decimal('cod_limit', 12, 2);
            $table->unsignedSmallInteger('max_parcels');
            $table->boolean('on_probation')->default(true);
            $table->string('device_id')->nullable();
            $table->string('payout_method', 20)->nullable();      // gcash, maya, bank
            $table->string('payout_account')->nullable();
            $table->text('status_reason')->nullable();
            $table->foreignId('created_by')->constrained('pr_staff');
            $table->foreignId('verified_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['station_hub_id', 'status']);
        });

        Schema::create('pr_rider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders')->cascadeOnDelete();
            $table->enum('doc_type', ['gov_id', 'drivers_license', 'or_cr', 'nbi_clearance', 'barangay_clearance', 'selfie_with_id']);
            $table->string('file_path');                           // private disk, never public
            $table->date('expiry_date')->nullable()->index();
            $table->foreignId('uploaded_by')->constrained('pr_staff');
            $table->timestamps();
        });

        Schema::create('pr_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_hub_id')->constrained('pr_hubs')->cascadeOnDelete();
            $table->string('name', 80);
            $table->foreignId('default_rider_id')->nullable()->constrained('pr_riders')->nullOnDelete();
            $table->foreignId('backup_rider_id')->nullable()->constrained('pr_riders')->nullOnDelete();
            $table->unsignedSmallInteger('max_parcels')->nullable();   // load cap for the zone per day
            $table->timestamps();
            $table->unique(['station_hub_id', 'name']);
        });

        Schema::create('pr_zone_barangays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('pr_zones')->cascadeOnDelete();
            $table->string('barangay_code', 9)->unique();          // a barangay belongs to exactly one zone
        });

        Schema::create('pr_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_no', 30)->unique();
            $table->foreignId('from_hub_id')->constrained('pr_hubs');
            $table->foreignId('to_hub_id')->constrained('pr_hubs');
            $table->enum('status', ['draft', 'dispatched', 'received', 'closed'])->default('draft')->index();
            $table->string('vehicle_plate', 20)->nullable();
            $table->string('driver_name')->nullable();
            $table->unsignedInteger('parcel_count')->default(0);
            $table->foreignId('created_by')->constrained('pr_staff');
            $table->foreignId('dispatched_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['to_hub_id', 'status']);
            $table->index(['from_hub_id', 'status']);
        });

        Schema::create('pr_manifest_parcels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained('pr_manifests')->cascadeOnDelete();
            $table->foreignId('parcel_id')->constrained('pr_parcels');
            $table->enum('scan_status', ['expected', 'scanned', 'missing', 'extra'])->default('expected');
            $table->timestamp('scanned_at')->nullable();
            $table->unique(['manifest_id', 'parcel_id']);
        });

        Schema::create('pr_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained('pr_parcels');
            $table->foreignId('rider_id')->constrained('pr_riders');
            $table->enum('type', ['pickup', 'delivery', 'return']);
            $table->foreignId('assigned_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->enum('status', ['assigned', 'out_for_delivery', 'completed', 'failed', 'reassigned', 'cancelled'])->default('assigned');
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->string('fail_reason', 30)->nullable();
            $table->text('fail_note')->nullable();
            $table->string('proof_photo_path')->nullable();
            $table->decimal('cod_collected', 12, 2)->nullable();
            $table->unsignedBigInteger('cod_remittance_id')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['rider_id', 'status']);
            $table->index(['parcel_id', 'status']);
        });

        Schema::create('pr_cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders');
            $table->foreignId('station_hub_id')->constrained('pr_hubs');
            $table->date('remit_date');
            $table->decimal('expected_amount', 12, 2);            // system total, never typed in
            $table->decimal('received_amount', 12, 2);            // counted cash
            $table->decimal('variance', 12, 2);                   // received - expected (negative = shortage)
            $table->enum('status', ['reconciled', 'disputed'])->index();
            $table->foreignId('received_by')->constrained('pr_staff');
            $table->foreignId('second_counter_id')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->unsignedBigInteger('hub_remittance_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('pr_hub_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_hub_id')->constrained('pr_hubs');
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('remitted_amount', 12, 2);
            $table->decimal('variance', 12, 2);
            $table->string('reference_no', 60)->nullable();       // bank or e-wallet deposit reference
            $table->enum('status', ['submitted', 'confirmed', 'disputed'])->default('submitted')->index();
            $table->foreignId('submitted_by')->constrained('pr_staff');
            $table->foreignId('confirmed_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pr_hub_remittances', 'pr_cod_remittances', 'pr_assignments', 'pr_manifest_parcels', 'pr_manifests',
            'pr_zone_barangays', 'pr_zones', 'pr_rider_documents', 'pr_riders'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->enum('payment_type', ['cod', 'prepaid'])->default('cod');
        });
    }
};
