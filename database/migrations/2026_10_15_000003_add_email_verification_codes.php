<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = ['pending', 'approved', 'disapproved', 'suspended', 'deactivated'];

    public function up(): void
    {
        // New registrations stay "unverified" until they confirm the one-time code sent to their email.
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

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');

        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (DB::table('users')->where('status', 'unverified')->exists()) {
            throw new RuntimeException('Cannot remove the unverified status while accounts still use it.');
        }
        $this->setUserStatuses(self::STATUSES);
    }

    private function setUserStatuses(array $statuses): void
    {
        $list = "'".implode("','", $statuses)."'";
        DB::statement("ALTER TABLE `users` MODIFY `status` ENUM({$list}) NOT NULL DEFAULT 'pending'");
    }
};
