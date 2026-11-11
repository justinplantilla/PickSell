<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riders keep their delivery pay out of the COD they collected: a rider remittance's expected
 * amount is the cash collected less the pay for those deliveries, and that pay is marked as
 * settled by the remittance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_cod_remittances', function (Blueprint $table) {
            // Rider pay taken out of the COD; expected_amount is what is left to hand over.
            $table->decimal('rider_pay_kept', 12, 2)->default(0)->after('expected_amount');
        });

        Schema::table('pr_rider_earnings', function (Blueprint $table) {
            $table->foreignId('cod_remittance_id')->nullable()->after('amount')->constrained('pr_cod_remittances')->nullOnDelete();
            $table->timestamp('settled_at')->nullable()->after('cod_remittance_id');
        });
    }

    public function down(): void
    {
        Schema::table('pr_rider_earnings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cod_remittance_id');
            $table->dropColumn('settled_at');
        });
        Schema::table('pr_cod_remittances', function (Blueprint $table) {
            $table->dropColumn('rider_pay_kept');
        });
    }
};
