<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rider document renewals.
 *
 * - A rider sends a new driver's license or OR/CR from the rider app, any time; it waits for the
 *   station hub manager. Approved, it replaces the old one, which is kept (replaced_at) as history.
 * - The OR/CR now has an expiry (the vehicle's registration). A rider with an expired license or
 *   OR/CR gets no new tasks until a renewed one is approved.
 *
 * OR/CRs on file before this have no expiry: they get one a year from today, marked estimated, so
 * the riders stay valid until the station checks the real date or the rider sends a new OR/CR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_rider_document_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('pr_riders')->cascadeOnDelete();
            $table->string('doc_type', 20);                 // drivers_license | or_cr
            $table->string('license_no', 30)->nullable();   // a new license only
            $table->date('expiry_date');
            $table->json('files');                          // [{path, side}] on the private disk
            $table->string('status', 10)->default('pending');   // pending | approved | rejected | replaced
            $table->string('reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['rider_id', 'doc_type', 'status']);
        });

        Schema::table('pr_rider_documents', function (Blueprint $table) {
            $table->boolean('expiry_estimated')->default(false)->after('expiry_date');
            $table->foreignId('document_update_id')->nullable()->after('uploaded_by')->constrained('pr_rider_document_updates')->nullOnDelete();
            $table->timestamp('replaced_at')->nullable()->after('document_update_id');
        });

        DB::table('pr_rider_documents')->where('doc_type', 'or_cr')->whereNull('expiry_date')
            ->update(['expiry_date' => today()->addYear()->toDateString(), 'expiry_estimated' => true]);
    }

    public function down(): void
    {
        Schema::table('pr_rider_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_update_id');
            $table->dropColumn(['expiry_estimated', 'replaced_at']);
        });
        Schema::dropIfExists('pr_rider_document_updates');
    }
};
