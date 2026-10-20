<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->setRoles(['buyer', 'seller', 'courier', 'logistics', 'admin']);
    }

    public function down(): void
    {
        $this->setRoles(['buyer', 'seller', 'courier', 'admin']);
    }

    private function setRoles(array $roles): void
    {
        $list = "'".implode("', '", $roles)."'";

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE users MODIFY role ENUM({$list}) NOT NULL DEFAULT 'buyer'"),
            // Postgres stores Laravel enums as a CHECK constraint named {table}_{column}_check.
            'pgsql' => DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check, ADD CONSTRAINT users_role_check CHECK (role IN ({$list}))"),
            default => null,
        };
    }
};
