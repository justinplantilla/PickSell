<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rider wallet and weekly payroll. Every pickup, delivery and return credits the rider's
 * wallet (pr_rider_earnings); riders hand over all the COD they collect. Each week (Monday to
 * Sunday) closes into a payslip per rider: the week's earnings less its deductions (cash short at
 * a remittance, a parcel the rider damaged or lost). The station manager reviews and releases it
 * (it releases itself on Tuesday), and its net becomes available to withdraw, any day. A negative
 * net carries into the balance. A rider can dispute a deduction; a disputed one is left out of
 * the payslip until the station manager decides it. Withdrawals are simulated (a mock reference).
 * Earnings already kept out of COD before the wallet (cod_remittance_id set) stay settled and
 * never enter a payslip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_rider_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders');
            $table->foreignId('station_hub_id')->constrained('pr_hubs');
            $table->date('week_start');                                   // Monday
            $table->date('week_end');                                     // Sunday
            $table->unsignedInteger('pickups')->default(0);
            $table->unsignedInteger('deliveries')->default(0);
            $table->unsignedInteger('returns')->default(0);
            $table->decimal('earned', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);                    // earned − deductions; may be negative
            $table->string('status', 20)->default('closed');              // closed | released
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('pr_staff')->nullOnDelete(); // null: released itself
            $table->timestamps();
            $table->unique(['rider_id', 'week_start']);
            $table->index(['station_hub_id', 'week_start', 'status']);
        });

        Schema::table('pr_rider_earnings', function (Blueprint $table) {
            $table->foreignId('payslip_id')->nullable()->after('settled_at')->constrained('pr_rider_payslips')->nullOnDelete();
        });

        Schema::create('pr_rider_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders');
            $table->foreignId('payslip_id')->nullable()->constrained('pr_rider_payslips')->nullOnDelete();
            $table->string('kind', 20);                                   // shortage | damage | lost | other
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->foreignId('cod_remittance_id')->nullable()->unique()->constrained('pr_cod_remittances')->nullOnDelete();
            $table->foreignId('parcel_id')->nullable()->constrained('pr_parcels')->nullOnDelete();
            $table->foreignId('logistics_issue_id')->nullable()->constrained('logistics_issues')->nullOnDelete();
            $table->string('status', 20)->default('active');              // active | disputed | waived
            $table->text('dispute_reason')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->string('decision', 20)->nullable();                   // upheld | waived
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['rider_id', 'status']);
        });

        Schema::create('pr_rider_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);                                 // gcash | maya | bank, as on file then
            $table->string('account', 64)->nullable();                    // masked, as on file then
            $table->string('reference', 40)->unique();                    // simulated payout reference
            $table->string('status', 20)->default('paid');                // paid (simulated)
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['rider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_rider_withdrawals');
        Schema::dropIfExists('pr_rider_deductions');
        Schema::table('pr_rider_earnings', fn (Blueprint $table) => $table->dropConstrainedForeignId('payslip_id'));
        Schema::dropIfExists('pr_rider_payslips');
    }
};
