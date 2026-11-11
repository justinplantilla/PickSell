<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The registered logistics companies a seller ships with. A seller with no rows has not chosen,
 * so every approved company is offered at checkout; once they choose, only theirs are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_logistics_companies', function (Blueprint $table) {
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('pr_companies')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['seller_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_logistics_companies');
    }
};
