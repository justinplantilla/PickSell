<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The review history of a logistics company: every decision by a PickSell administrator
 * (approved, rejected, suspended, reinstated) and every resubmission by the company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_company_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('pr_companies')->cascadeOnDelete();
            $table->enum('decision', ['approved', 'rejected', 'suspended', 'reinstated', 'resubmitted']);
            $table->string('reason', 1000)->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();   // a PickSell admin
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_company_reviews');
    }
};
