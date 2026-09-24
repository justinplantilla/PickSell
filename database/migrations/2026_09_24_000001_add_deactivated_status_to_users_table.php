<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY status ENUM('pending', 'approved', 'disapproved', 'suspended', 'deactivated') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE users SET status = 'suspended' WHERE status = 'deactivated'");
            DB::statement("ALTER TABLE users MODIFY status ENUM('pending', 'approved', 'disapproved', 'suspended') NOT NULL DEFAULT 'pending'");
        }
    }
};