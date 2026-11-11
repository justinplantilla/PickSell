<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Problems with the registered logistics companies, as admin follows them up: parcel exceptions
 * their hubs raise, lost and returned parcels, and reports from buyers and sellers. An issue
 * found to be the carrier's fault can carry a claim, deducted from the company's share.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('pr_companies');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_shipment_id')->nullable()->constrained('order_shipments')->nullOnDelete();
            $table->foreignId('parcel_id')->nullable()->constrained('pr_parcels')->nullOnDelete();
            $table->foreignId('exception_id')->nullable()->unique()->constrained('pr_exceptions')->nullOnDelete();
            $table->string('source', 20);                 // exception | parcel | report
            $table->string('type', 30);
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_role', 20)->nullable(); // buyer | seller | logistics | system
            $table->text('description')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('status', 20)->default('open'); // open | escalated | resolved
            $table->timestamp('escalated_at')->nullable();
            $table->text('company_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('fault', 20)->nullable();       // carrier | seller | buyer | none
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['company_id', 'status']);
            $table->index(['parcel_id', 'type']);
        });

        Schema::create('logistics_issue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('logistics_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_issue_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('pr_companies');
            $table->decimal('item_value', 12, 2);
            $table->decimal('shipping_fee', 12, 2);
            $table->decimal('amount', 12, 2);             // what is deducted: item value + shipping fee, capped
            $table->boolean('capped')->default(false);
            $table->string('status', 20)->default('charged'); // charged | waived
            $table->text('waive_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_claims');
        Schema::dropIfExists('logistics_issue_events');
        Schema::dropIfExists('logistics_issues');
    }
};
