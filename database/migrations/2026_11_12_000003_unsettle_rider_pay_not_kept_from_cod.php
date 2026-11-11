<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The first version of 2026_11_12_000002 marked all earnings up to a rider's last remittance as
 * settled, pickups included. Only delivery pay kept out of a remittance is settled, so clear the
 * rest: that pay was never taken from any cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pr_rider_earnings')->whereNull('cod_remittance_id')->whereNotNull('settled_at')->update(['settled_at' => null]);
    }

    public function down(): void
    {
        //
    }
};
