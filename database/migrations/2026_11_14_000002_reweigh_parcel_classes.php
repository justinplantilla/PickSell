<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New weight bands for the parcel classes, and EXTRA_BULKY: a class may add a per-kg pay for each
 * started kg above its lower bound (₱60 + ₱4/kg above 20 kg). The same pay now goes to the pickup
 * rider as to the delivery rider. Placed orders keep the rider pay saved on them.
 */
return new class extends Migration
{
    /** code => [above kg, up to kg, rider pay, per-kg pay] */
    private const CLASSES = [
        'STANDARD' => [0, 5, 15, 0],
        'HEAVY' => [5, 10, 40, 0],
        'BULKY' => [10, 20, 60, 0],
        'EXTRA_BULKY' => [20, 50, 60, 4],
    ];

    /** What the classes were before. */
    private const BEFORE = [
        'STANDARD' => [0, 10, 15],
        'HEAVY' => [10, 20, 25],
        'BULKY' => [20, 50, 50],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('pr_parcel_classes', 'per_kg_pay')) {
            Schema::table('pr_parcel_classes', function (Blueprint $table) {
                $table->decimal('per_kg_pay', 10, 2)->default(0)->after('rider_pay');   // each started kg above min_kg_exclusive
            });
        }

        foreach (self::CLASSES as $code => [$above, $upTo, $pay, $perKg]) {
            DB::table('pr_parcel_classes')->updateOrInsert(['code' => $code], [
                'min_kg_exclusive' => $above, 'max_kg_inclusive' => $upTo, 'rider_pay' => $pay, 'per_kg_pay' => $perKg,
                'is_active' => true, 'updated_at' => now(),
            ]);
        }
        DB::table('pr_parcel_classes')->where('code', 'EXTRA_BULKY')->whereNull('created_at')->update(['created_at' => now()]);
    }

    public function down(): void
    {
        DB::table('pr_parcel_classes')->where('code', 'EXTRA_BULKY')->delete();
        foreach (self::BEFORE as $code => [$above, $upTo, $pay]) {
            DB::table('pr_parcel_classes')->where('code', $code)
                ->update(['min_kg_exclusive' => $above, 'max_kg_inclusive' => $upTo, 'rider_pay' => $pay, 'updated_at' => now()]);
        }
        Schema::table('pr_parcel_classes', fn (Blueprint $table) => $table->dropColumn('per_kg_pay'));
    }
};
