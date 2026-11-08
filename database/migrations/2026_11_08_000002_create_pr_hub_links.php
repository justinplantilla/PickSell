<?php

use App\Pickrush\Services\HubLinkBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links between neighboring province hubs, by land or ferry. Province-to-province trips only run
 * along an active link, and parcels relay hub by hub along the fastest chain of links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_hub_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_a_id')->constrained('pr_hubs')->cascadeOnDelete();   // the lower hub id of the pair
            $table->foreignId('hub_b_id')->constrained('pr_hubs')->cascadeOnDelete();
            $table->enum('mode', ['land', 'sea'])->default('land');
            $table->decimal('travel_hours', 5, 1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hub_a_id', 'hub_b_id']);
            $table->index('hub_b_id');
        });

        // The starting links are added by 2026_11_09_000001_create_pr_companies, once links have a company.
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_hub_links');
    }
};
