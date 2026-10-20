<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pickrush core: hubs, staff, parcels, the append-only parcel event log, and the audit log.
 * Everything Pickrush owns lives in pr_* tables; the marketplace never writes to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_hubs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();             // P-<province psgc> or S-<municipality psgc>
            $table->string('name');
            $table->enum('type', ['province', 'station']);
            $table->foreignId('parent_hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete();
            $table->string('psgc_code', 9);                    // province/district code, or city/municipality code
            $table->string('region_code', 9)->index();
            $table->string('province_name');
            $table->string('municipality_name')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'is_active']);
            $table->unique(['type', 'psgc_code']);         // Manila and the First District share 133900000
        });

        Schema::create('pr_staff', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('mobile_no', 20)->nullable();
            $table->string('password');
            $table->enum('role', ['super_admin', 'finance', 'support', 'hub_manager', 'dispatcher', 'sorter']);
            $table->foreignId('hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sample')->default(false);     // placeholder accounts from the dev seeder
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index(['hub_id', 'role']);
        });

        Schema::create('pr_parcels', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no', 24)->unique();
            $table->string('source', 20)->default('picksell');   // picksell or direct (non-marketplace shippers)
            $table->string('external_ref')->nullable();          // e.g. the Picksell order id
            $table->foreignId('origin_hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete();
            $table->foreignId('dest_hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete();
            $table->foreignId('current_hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete();
            $table->foreignId('next_hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete();
            $table->string('current_status', 40)->nullable()->index();   // null only until the first event
            $table->string('shipper_name');
            $table->string('shipper_mobile', 20)->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('pickup_barangay_code', 9)->nullable();
            $table->string('pickup_municipality_code', 9)->nullable();
            $table->string('consignee_name');
            $table->string('consignee_mobile', 20)->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_barangay_code', 9)->nullable()->index();
            $table->string('delivery_municipality_code', 9)->nullable();
            $table->string('item_description')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->string('size_category', 20)->nullable();
            $table->enum('payment_type', ['cod', 'prepaid'])->default('cod');
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->boolean('is_return')->default(false);         // travelling back to the shipper
            $table->boolean('needs_address_fix')->default(false)->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_ref']);
            $table->index('dest_hub_id');
            $table->index('current_hub_id');
        });

        Schema::create('pr_parcel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained('pr_parcels')->cascadeOnDelete();
            $table->string('status', 40);
            $table->foreignId('hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete();
            $table->foreignId('to_hub_id')->nullable()->constrained('pr_hubs')->nullOnDelete();
            $table->unsignedBigInteger('rider_id')->nullable()->index();
            $table->string('actor_type', 20);                   // staff, rider, system, picksell
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->timestamp('occurred_at');                   // when the scan happened (offline scans replay later)
            $table->timestamp('created_at')->useCurrent();
            $table->index(['parcel_id', 'occurred_at']);
        });

        Schema::create('pr_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('actor_hub_id')->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_audit_logs');
        Schema::dropIfExists('pr_parcel_events');
        Schema::dropIfExists('pr_parcels');
        Schema::dropIfExists('pr_staff');
        Schema::dropIfExists('pr_hubs');
    }
};
