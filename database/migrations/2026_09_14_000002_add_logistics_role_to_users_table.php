<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('buyer', 'seller', 'courier', 'logistics', 'admin') NOT NULL DEFAULT 'buyer'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('buyer', 'seller', 'courier', 'admin') NOT NULL DEFAULT 'buyer'");
    }
};
