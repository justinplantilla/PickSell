<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A parcel the buyer did not receive goes back to the seller, who pays its original shipping fee
 * to the return rider (return_fee, cash on hand-back; no PickSell commission). A seller who does
 * not take it back has it held at the station (return_refused_at: the first refused hand-back);
 * the company reports it to PickSell, which strikes the seller (seller_strikes, 90 days). Three
 * live strikes put the shop on hold (selling_held_at) until PickSell lifts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_parcels', function (Blueprint $table) {
            $table->decimal('return_fee', 10, 2)->nullable();          // collected from the seller on hand-back
            $table->timestamp('return_refused_at')->nullable();         // the seller first did not take it back
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('selling_held_at')->nullable();
            $table->string('selling_hold_reason', 255)->nullable();
        });

        Schema::create('seller_strikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('logistics_issue_id')->nullable()->unique()->constrained('logistics_issues')->nullOnDelete();
            $table->string('reason', 255);
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['seller_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_strikes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['selling_held_at', 'selling_hold_reason']));
        Schema::table('pr_parcels', fn (Blueprint $table) => $table->dropColumn(['return_fee', 'return_refused_at']));
    }
};
