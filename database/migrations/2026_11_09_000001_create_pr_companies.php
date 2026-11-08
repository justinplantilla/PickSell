<?php

use App\Pickrush\Services\HubLinkBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics companies. Pickrush becomes company #1 (approved, the default carrier at checkout) and
 * every existing hub, staff account, rider, parcel and hub link is backfilled to it. Hub codes and
 * PSGC slots are unique per company, so another company may run a station in the same town.
 * Child records (zones, trips, remittances, ...) belong to a company through their hub or parcel.
 */
return new class extends Migration
{
    private const OWNED = ['pr_hubs', 'pr_staff', 'pr_riders', 'pr_parcels', 'pr_hub_links'];

    public function up(): void
    {
        Schema::create('pr_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('slug', 160)->unique();
            $table->string('tracking_prefix', 4)->unique();   // starts every tracking number, e.g. PR
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->boolean('is_default')->default(false);    // pre-selected carrier at checkout (Pickrush)
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        $pickrushId = DB::table('pr_companies')->insertGetId([
            'name' => 'Pickrush Logistics',
            'slug' => 'pickrush',
            'tracking_prefix' => 'PR',
            'status' => 'approved',
            'is_default' => true,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::OWNED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('pr_companies');
            });
            DB::table($name)->update(['company_id' => $pickrushId]);
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }

        // Audit entries keep the company they happened in; system entries without one stay null.
        Schema::table('pr_audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
        });
        DB::table('pr_audit_logs')->update(['company_id' => $pickrushId]);

        Schema::table('pr_hubs', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropUnique(['type', 'psgc_code']);
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'type', 'psgc_code']);
        });

        app(HubLinkBuilder::class)->build();   // Pickrush's starting links, for the hubs that already exist
    }

    public function down(): void
    {
        Schema::table('pr_hubs', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'code']);
            $table->dropUnique(['company_id', 'type', 'psgc_code']);
        });
        // Only Pickrush's rows can go back to the single-company shape.
        $others = DB::table('pr_companies')->where('is_default', false)->pluck('id');
        foreach (array_reverse(self::OWNED) as $name) {
            DB::table($name)->whereIn('company_id', $others)->delete();
        }
        Schema::table('pr_hubs', function (Blueprint $table) {
            $table->unique('code');
            $table->unique(['type', 'psgc_code']);
        });

        Schema::table('pr_audit_logs', fn (Blueprint $table) => $table->dropColumn('company_id'));
        foreach (array_reverse(self::OWNED) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
        Schema::dropIfExists('pr_companies');
    }
};
