<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A logistics company paying PickSell what it owes for delivered PickSell parcels: each parcel's
 * COD less the rider pay and the company's share of the delivery fee, plus the claims PickSell
 * charged it. The company's HQ finance settles everything collected so far in one deposit (slip,
 * reference used once); a PickSell admin confirms it or disputes it, and a disputed settlement is
 * topped up or contested, as station remittances are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_company_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('pr_companies');
            $table->foreignId('settles_id')->nullable()->constrained('pr_company_settlements')->nullOnDelete();   // a top-up for a disputed settlement
            $table->unsignedInteger('parcel_count')->default(0);
            $table->decimal('claims_amount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);                         // what the company deposits: parcels owed + claims
            $table->string('reference_no', 60);
            $table->string('reference_key', 60)->unique();            // the reference without spaces, dashes or case
            $table->string('slip_path');
            $table->string('status', 20)->default('submitted')->index();   // submitted, confirmed, disputed, settled
            $table->text('note')->nullable();
            $table->decimal('bank_amount', 12, 2)->nullable();        // what PickSell's bank showed, on a dispute
            $table->text('company_response')->nullable();             // why the company contests a dispute
            $table->string('contest_path')->nullable();
            $table->timestamp('contested_at')->nullable();
            $table->foreignId('submitted_by')->constrained('pr_staff');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();   // the PickSell admin
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->foreignId('company_settlement_id')->nullable()->after('logistics_share')->constrained('pr_company_settlements')->nullOnDelete();
        });

        Schema::table('logistics_claims', function (Blueprint $table) {
            $table->foreignId('company_settlement_id')->nullable()->after('status')->constrained('pr_company_settlements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_claims', fn (Blueprint $table) => $table->dropConstrainedForeignId('company_settlement_id'));
        Schema::table('pr_parcels', fn (Blueprint $table) => $table->dropConstrainedForeignId('company_settlement_id'));
        Schema::dropIfExists('pr_company_settlements');
    }
};
