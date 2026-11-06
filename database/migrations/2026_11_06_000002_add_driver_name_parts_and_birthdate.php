<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drivers give their name and birthdate like riders do: first and last name (full_name kept in
 * step for the rest of Pickrush) and a birthdate showing they are 18 or older. Existing names are
 * split at the last space; existing drivers get their birthdate the next time they are edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_drivers', function (Blueprint $table) {
            $table->string('first_name', 60)->nullable()->after('hub_id');
            $table->string('last_name', 60)->nullable()->after('first_name');
            $table->date('birthdate')->nullable()->after('full_name');
        });

        DB::table('pr_drivers')->orderBy('id')->each(function ($driver) {
            $name = trim($driver->full_name);
            $cut = strrpos($name, ' ');
            DB::table('pr_drivers')->where('id', $driver->id)->update([
                'first_name' => $cut === false ? $name : substr($name, 0, $cut),
                'last_name' => $cut === false ? '' : substr($name, $cut + 1),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('pr_drivers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'birthdate']);
        });
    }
};
