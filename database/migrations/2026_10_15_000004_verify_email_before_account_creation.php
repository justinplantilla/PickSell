<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyers now verify their email with a one-time code on the registration form, before the account exists,
 * so accounts no longer wait in an "unverified" status and codes live in the cache instead of a table.
 */
return new class extends Migration
{
    private const STATUSES = ['pending', 'approved', 'disapproved', 'suspended', 'deactivated'];

    public function up(): void
    {
        Schema::dropIfExists('email_verification_codes');

        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Any account left unverified by the short-lived post-registration flow goes to normal review.
        DB::table('users')->where('status', 'unverified')->update(['status' => 'pending']);
        $this->setUserStatuses(self::STATUSES);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->setUserStatuses([...self::STATUSES, 'unverified']);
        }

        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'consumed_at']);
        });
    }

    private function setUserStatuses(array $statuses): void
    {
        $list = "'".implode("','", $statuses)."'";
        DB::statement("ALTER TABLE `users` MODIFY `status` ENUM({$list}) NOT NULL DEFAULT 'pending'");
    }
};
