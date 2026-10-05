<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('opened_by')->constrained('users');
            // Extension to the spec: the listing a case is about, when it concerns one product.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('status', 30)->default('open');
            $table->string('severity', 20)->default('medium');
            $table->text('description');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['severity', 'status']);
        });

        // Append-only compliance record per seller: warnings, suspensions, reinstatements, case
        // lifecycle and evidence. Each row keeps its reason and attachments.
        Schema::create('compliance_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('compliance_case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 30);
            $table->text('reason')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'created_at']);
            $table->index(['seller_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_actions');
        Schema::dropIfExists('compliance_cases');
    }
};
