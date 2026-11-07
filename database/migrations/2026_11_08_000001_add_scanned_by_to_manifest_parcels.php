<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who scanned each parcel off a trip at the receiving hub, shown on the trip page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_manifest_parcels', function (Blueprint $table) {
            $table->foreignId('scanned_by')->nullable()->after('scanned_at')->constrained('pr_staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pr_manifest_parcels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scanned_by');
        });
    }
};
