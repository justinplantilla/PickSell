<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller vacation mode. While it is on, the seller's products leave the shop listings and cannot be
 * ordered; the store page stays open, and buyers who message the seller get one auto-reply per vacation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('vacation_mode')->default(false)->after('bir_certificate');
            $table->timestamp('vacation_started_at')->nullable()->after('vacation_mode'); // auto-replies go out once per buyer since then
            $table->string('vacation_message', 255)->nullable()->after('vacation_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vacation_mode', 'vacation_started_at', 'vacation_message']);
        });
    }
};
