<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->setStatuses(['pending', 'approved', 'disapproved', 'suspended', 'deactivated']);
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement("UPDATE users SET status = 'suspended' WHERE status = 'deactivated'");
        }
        $this->setStatuses(['pending', 'approved', 'disapproved', 'suspended']);
    }

    private function setStatuses(array $statuses): void
    {
        $list = "'".implode("', '", $statuses)."'";

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE users MODIFY status ENUM({$list}) NOT NULL DEFAULT 'pending'"),
            'pgsql' => DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check, ADD CONSTRAINT users_status_check CHECK (status IN ({$list}))"),
            default => null,
        };
    }
};
