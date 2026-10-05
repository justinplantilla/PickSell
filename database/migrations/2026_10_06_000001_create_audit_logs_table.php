<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 32)->nullable();
            $table->string('action', 64)->index();          // e.g. user.registration_approved, authorization.denied
            $table->string('permission', 64)->nullable();   // the Permission that authorized (or was missing)
            $table->nullableMorphs('subject');
            $table->json('changes')->nullable();            // {field: {from, to}}
            $table->json('metadata')->nullable();           // reason, notes, route, ...
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
