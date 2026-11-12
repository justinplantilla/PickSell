<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two hand-overs of cash get a second party. A rider remittance waits for the rider to confirm the
 * staff count in the rider app (awaiting_rider), or the rider rejects it (rejected) and the cash is
 * counted again. A station remittance to HQ carries a deposit slip and a reference that can be used
 * only once, and HQ finance records what the bank statement shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_cod_remittances', function (Blueprint $table) {
            // awaiting_rider, reconciled, disputed, rejected. Stays a string on rollback: rows may hold the new statuses.
            $table->string('status', 20)->change();
            $table->timestamp('rider_responded_at')->nullable()->after('second_counter_id');
            $table->text('rider_note')->nullable()->after('rider_responded_at');   // why the rider rejected the count
        });

        Schema::table('pr_hub_remittances', function (Blueprint $table) {
            // reference_no without spaces, dashes or case: "bdo 0042" and "BDO-0042" are the same deposit.
            $table->string('reference_key', 60)->nullable()->after('reference_no');
            $table->string('slip_path')->nullable()->after('reference_key');
            $table->decimal('bank_amount', 12, 2)->nullable()->after('variance');   // what HQ finance saw on the bank statement
        });

        // Older remittances keep their references; a repeat among them is told apart by its id so the index holds.
        $seen = [];
        foreach (DB::table('pr_hub_remittances')->orderBy('id')->get(['id', 'reference_no']) as $row) {
            $key = self::key((string) $row->reference_no) ?: "LEGACY{$row->id}";
            $key = isset($seen[$key]) ? substr($key, 0, 50)."DUP{$row->id}" : $key;
            $seen[$key] = true;
            DB::table('pr_hub_remittances')->where('id', $row->id)->update(['reference_key' => $key]);
        }

        Schema::table('pr_hub_remittances', function (Blueprint $table) {
            $table->unique('reference_key');
        });
    }

    public function down(): void
    {
        Schema::table('pr_hub_remittances', function (Blueprint $table) {
            $table->dropUnique(['reference_key']);
            $table->dropColumn(['reference_key', 'slip_path', 'bank_amount']);
        });

        Schema::table('pr_cod_remittances', function (Blueprint $table) {
            $table->dropColumn(['rider_responded_at', 'rider_note']);
        });
    }

    private static function key(string $reference): string
    {
        return substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $reference)), 0, 60);
    }
};
