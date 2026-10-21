<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** HQ-editable rates and policies; each row overrides one config('pickrush.*') default. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();               // e.g. policies.max_delivery_attempts
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_settings');
    }
};
