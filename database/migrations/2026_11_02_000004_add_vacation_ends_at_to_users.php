<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacations end on their own: the seller picks a return date (within the monthly allowance), and
 * from then on the seller counts as back even before the clean-up command clears vacation_mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('vacation_ends_at')->nullable()->after('vacation_started_at');
        });

        // Sellers already away get the longest vacation allowed, counted from when it began.
        DB::table('users')->where('vacation_mode', true)->orderBy('id')->each(function ($user): void {
            $start = $user->vacation_started_at ? Carbon::parse($user->vacation_started_at) : now();
            DB::table('users')->where('id', $user->id)->update(['vacation_ends_at' => $start->addDays(30)->startOfDay()]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vacation_ends_at');
        });
    }
};
