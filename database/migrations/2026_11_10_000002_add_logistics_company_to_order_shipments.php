<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The logistics company the buyer chose for a seller's parcel at checkout (Pickrush by default).
 * Shipments placed before buyers could choose were all Pickrush's.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_shipments', 'logistics_company_id')) {
            Schema::table('order_shipments', function (Blueprint $table) {
                $table->foreignId('logistics_company_id')->nullable()->after('seller_id')->constrained('pr_companies');
            });
        }
        DB::table('order_shipments')->whereNull('logistics_company_id')
            ->update(['logistics_company_id' => DB::table('pr_companies')->where('is_default', true)->value('id')]);
    }

    public function down(): void
    {
        Schema::table('order_shipments', fn (Blueprint $table) => $table->dropConstrainedForeignId('logistics_company_id'));
    }
};
