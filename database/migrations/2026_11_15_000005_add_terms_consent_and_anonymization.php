<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof of consent: which version of the Terms of Service and Privacy Policy (config legal.version)
 * each account agreed to, and when. Marketplace users also get anonymized_at: a deleted account
 * keeps its order and money records for the other party, tax and disputes, but loses its
 * personal details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('terms_version', 20)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
        });
        // Logistics companies already record theirs (pr_companies, 2026_11_09_000002).
        Schema::table('pr_riders', function (Blueprint $table) {
            $table->string('terms_version', 20)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_version', 'terms_accepted_at', 'anonymized_at']);
        });
        Schema::table('pr_riders', function (Blueprint $table) {
            $table->dropColumn(['terms_version', 'terms_accepted_at']);
        });
    }
};
