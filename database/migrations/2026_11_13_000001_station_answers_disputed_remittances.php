<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A station remittance HQ disputed gets an answer from the station: a top-up deposit for the
 * shortfall (a remittance of its own that settles the disputed one once HQ confirms it), or a
 * contest with the station's explanation and evidence, which sends it back to HQ to check again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_hub_remittances', function (Blueprint $table) {
            // submitted, confirmed, disputed, settled. Stays a string on rollback: rows may hold "settled".
            $table->string('status', 20)->default('submitted')->change();
            $table->foreignId('settles_id')->nullable()->after('station_hub_id')->constrained('pr_hub_remittances')->nullOnDelete();
            $table->text('station_response')->nullable()->after('note');   // why the station contests the dispute
            $table->string('contest_path')->nullable()->after('station_response');
            $table->timestamp('contested_at')->nullable()->after('contest_path');
        });
    }

    public function down(): void
    {
        Schema::table('pr_hub_remittances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settles_id');
            $table->dropColumn(['station_response', 'contest_path', 'contested_at']);
        });
    }
};
