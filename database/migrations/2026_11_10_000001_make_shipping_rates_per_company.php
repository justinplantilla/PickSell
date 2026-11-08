<?php

use App\Pickrush\Models\Company;
use App\Pickrush\Services\Shipping\RateCardCopier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every logistics company prices its own shipping. The rate card (price levels, the route grid
 * and the dated rate rows) gains a company; today's card is Pickrush's, and each company that is
 * already approved starts from a copy of it. Regions, the province map and parcel classes stay
 * shared by the whole network.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pickrush = (int) DB::table('pr_companies')->where('is_default', true)->value('id');

        // Dated rate rows: the company leads the unique key and the lookup index. MySQL cannot undo
        // schema changes, so every step checks whether it already ran (a failed run can be resumed).
        foreach (['pr_shipping_rates' => ['max_weight_kg', 'effective_from'], 'pr_shipping_overweight_rates' => ['effective_from']] as $name => $tail) {
            $unique = $name === 'pr_shipping_rates' ? 'pr_shipping_rates_unique' : 'pr_shipping_overweight_unique';
            $lookup = $name === 'pr_shipping_rates' ? 'pr_shipping_rates_lookup' : 'pr_shipping_overweight_lookup';
            if (! Schema::hasColumn($name, 'company_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->foreignId('company_id')->nullable()->after('id')->constrained('pr_companies'));
            }
            // The region foreign keys keep their own indexes once the unique key and lookup index move.
            foreach (['origin_region', 'destination_region'] as $column) {
                if (! Schema::hasIndex($name, [$column])) {
                    Schema::table($name, fn (Blueprint $table) => $table->index($column));
                }
            }
            DB::table($name)->whereNull('company_id')->update(['company_id' => $pickrush]);
            Schema::table($name, fn (Blueprint $table) => $table->unsignedBigInteger('company_id')->nullable(false)->change());

            if (Schema::hasIndex($name, $unique) && ! Schema::hasIndex($name, array_merge(['company_id', 'origin_region', 'destination_region'], $tail), 'unique')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropUnique($unique));
            }
            if (Schema::hasIndex($name, $lookup) && ! Schema::hasIndex($name, ['company_id', 'destination_region', 'origin_region', 'effective_from'])) {
                Schema::table($name, fn (Blueprint $table) => $table->dropIndex($lookup));
            }
            if (! Schema::hasIndex($name, $unique)) {
                Schema::table($name, fn (Blueprint $table) => $table->unique(array_merge(['company_id', 'origin_region', 'destination_region'], $tail), $unique));
            }
            if (! Schema::hasIndex($name, $lookup)) {
                Schema::table($name, fn (Blueprint $table) => $table->index(['company_id', 'destination_region', 'origin_region', 'effective_from'], $lookup));
            }
        }

        // Price levels and the route grid are keyed by company too (skipped when already done).
        if (! Schema::hasColumn('pr_shipping_levels', 'company_id')) {
            $this->rebuildLevels($pickrush);
        }

        foreach (Company::where('status', 'approved')->where('is_default', false)->get() as $company) {
            app(RateCardCopier::class)->copy($company);
        }
    }

    /**
     * Their keys cannot be changed in place on every database, so each table is rebuilt with the
     * company in its key and Pickrush's rows are put back.
     */
    private function rebuildLevels(int $pickrush): void
    {
        $levels = DB::table('pr_shipping_levels')->get();
        $fees = DB::table('pr_shipping_level_fees')->get();
        $grid = DB::table('pr_shipping_route_levels')->get();
        Schema::drop('pr_shipping_route_levels');
        Schema::drop('pr_shipping_level_fees');
        Schema::drop('pr_shipping_levels');

        Schema::create('pr_shipping_levels', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('pr_companies')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('name');
            $table->decimal('over_kg', 6, 2)->default(10);
            $table->decimal('per_kg_fee', 10, 2);
            $table->timestamps();
            $table->primary(['company_id', 'level']);
        });
        Schema::create('pr_shipping_level_fees', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('pr_companies')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->decimal('max_weight_kg', 6, 2);
            $table->decimal('fee', 10, 2);
            $table->primary(['company_id', 'level', 'max_weight_kg']);
            $table->foreign(['company_id', 'level'])->references(['company_id', 'level'])->on('pr_shipping_levels')->cascadeOnDelete();
        });
        Schema::create('pr_shipping_route_levels', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('pr_companies')->cascadeOnDelete();
            $table->string('origin_region', 10);
            $table->string('destination_region', 10);
            $table->unsignedTinyInteger('level');
            $table->timestamp('updated_at')->nullable();
            $table->primary(['company_id', 'origin_region', 'destination_region']);
            $table->foreign('origin_region')->references('code')->on('pr_shipping_regions');
            $table->foreign('destination_region')->references('code')->on('pr_shipping_regions');
            $table->foreign(['company_id', 'level'])->references(['company_id', 'level'])->on('pr_shipping_levels');
        });

        foreach (['pr_shipping_levels' => $levels, 'pr_shipping_level_fees' => $fees, 'pr_shipping_route_levels' => $grid] as $name => $rows) {
            $rows = $rows->map(fn ($row) => ['company_id' => $pickrush] + (array) $row)->all();
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($name)->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        $pickrush = (int) DB::table('pr_companies')->where('is_default', true)->value('id');
        foreach (['pr_shipping_route_levels', 'pr_shipping_level_fees', 'pr_shipping_levels', 'pr_shipping_overweight_rates', 'pr_shipping_rates'] as $name) {
            DB::table($name)->where('company_id', '!=', $pickrush)->delete();
        }

        foreach (['pr_shipping_rates' => ['max_weight_kg', 'effective_from'], 'pr_shipping_overweight_rates' => ['effective_from']] as $name => $tail) {
            $unique = $name === 'pr_shipping_rates' ? 'pr_shipping_rates_unique' : 'pr_shipping_overweight_unique';
            $lookup = $name === 'pr_shipping_rates' ? 'pr_shipping_rates_lookup' : 'pr_shipping_overweight_lookup';
            Schema::table($name, function (Blueprint $table) use ($unique, $lookup) {
                $table->dropUnique($unique);
                $table->dropIndex($lookup);
            });
            Schema::table($name, fn (Blueprint $table) => $table->dropForeign(['company_id']));
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('company_id'));
            Schema::table($name, function (Blueprint $table) use ($tail, $unique, $lookup) {
                $table->unique(array_merge(['origin_region', 'destination_region'], $tail), $unique);
                $table->index(['destination_region', 'origin_region', 'effective_from'], $lookup);
                $table->dropIndex(['origin_region']);
                $table->dropIndex(['destination_region']);
            });
        }
        // The price levels and route grid go back to Pickrush's single card, keyed without a company.
        $levels = DB::table('pr_shipping_levels')->get();
        $fees = DB::table('pr_shipping_level_fees')->get();
        $grid = DB::table('pr_shipping_route_levels')->get();
        Schema::drop('pr_shipping_route_levels');
        Schema::drop('pr_shipping_level_fees');
        Schema::drop('pr_shipping_levels');
        Schema::create('pr_shipping_levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->primary();
            $table->string('name');
            $table->decimal('over_kg', 6, 2)->default(10);
            $table->decimal('per_kg_fee', 10, 2);
            $table->timestamps();
        });
        Schema::create('pr_shipping_level_fees', function (Blueprint $table) {
            $table->unsignedTinyInteger('level');
            $table->decimal('max_weight_kg', 6, 2);
            $table->decimal('fee', 10, 2);
            $table->primary(['level', 'max_weight_kg']);
            $table->foreign('level')->references('level')->on('pr_shipping_levels')->cascadeOnDelete();
        });
        Schema::create('pr_shipping_route_levels', function (Blueprint $table) {
            $table->string('origin_region', 10);
            $table->string('destination_region', 10);
            $table->unsignedTinyInteger('level');
            $table->timestamp('updated_at')->nullable();
            $table->primary(['origin_region', 'destination_region']);
            $table->foreign('origin_region')->references('code')->on('pr_shipping_regions');
            $table->foreign('destination_region')->references('code')->on('pr_shipping_regions');
            $table->foreign('level')->references('level')->on('pr_shipping_levels');
        });
        foreach (['pr_shipping_levels' => $levels, 'pr_shipping_level_fees' => $fees, 'pr_shipping_route_levels' => $grid] as $name => $rows) {
            DB::table($name)->insert($rows->map(fn ($row) => array_diff_key((array) $row, ['company_id' => true]))->all());
        }
    }
};
