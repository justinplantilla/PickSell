<?php

namespace App\Pickrush\Services\Shipping;

use App\Pickrush\Models\AuditLog;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns the five price levels and the 5 × 5 route grid into dated rate rows for every
 * (seller region → buyer region) pair: price(origin, destination, tier) = level price of
 * level(origin, destination). Nothing is multiplied or rounded; every price is a level price.
 *
 * Generic rows (no origin) are the fallback and always equal the NCR-origin route prices.
 * Idempotent: a route whose rows already carry the right prices is left alone. A changed price
 * never rewrites history: the old row ends the day before and a new row starts on the date given.
 */
class RouteRateGenerator
{
    /** When the first route prices took effect. */
    public const FIRST_ROUTE_DATE = '2026-11-05';

    public const ORIGIN_OF_GENERIC = 'NCR';

    /**
     * @param  bool  $updateGeneric  also move the generic rows to the NCR-origin prices (HQ edits);
     *                               otherwise they must already match, or nothing is generated
     * @return array{created: int, ended: int, updated: int, unchanged: int}
     */
    public function generate(string $from, bool $updateGeneric = false): array
    {
        $from = Carbon::parse($from)->toDateString();

        return DB::transaction(function () use ($from, $updateGeneric) {
            $regions = DB::table('pr_shipping_regions')->orderBy('code')->pluck('code')->all();
            $levels = $this->levels();
            $grid = $this->grid();
            self::validateLevels($levels);
            self::validateGrid($grid, $regions, array_keys($levels));
            $this->checkBase($regions, $levels, $grid, $from, $updateGeneric);

            $counts = ['created' => 0, 'ended' => 0, 'updated' => 0, 'unchanged' => 0];
            $routes = [];
            foreach ($regions as $origin) {
                foreach ($regions as $destination) {
                    $routes[] = [$origin, $destination, $grid[$origin][$destination]];
                }
            }
            if ($updateGeneric) {
                foreach ($regions as $destination) {
                    $routes[] = [null, $destination, $grid[self::ORIGIN_OF_GENERIC][$destination]];
                }
            }

            foreach ($routes as [$origin, $destination, $level]) {
                foreach ($levels[$level]['fees'] as $kg => $fee) {
                    $this->sync('pr_shipping_rates', ['max_weight_kg' => $kg], 'fee', $origin, $destination, $fee, $from, $counts);
                }
                $this->sync('pr_shipping_overweight_rates', [], 'per_kg_fee', $origin, $destination, $levels[$level]['per_kg'], $from, $counts,
                    ['over_kg' => $levels[$level]['over_kg']]);
            }

            return $counts;
        });
    }

    /**
     * Save a new route grid and generate its prices from $from.
     *
     * @param  array<string, array<string, int>>  $grid  origin => destination => level
     */
    public function updateGrid(array $grid, string $from): array
    {
        return DB::transaction(function () use ($grid, $from) {
            $regions = DB::table('pr_shipping_regions')->orderBy('code')->pluck('code')->all();
            $grid = array_map(fn ($row) => array_map('intval', $row), $grid);
            self::validateGrid($grid, $regions, array_keys($this->levels()));

            $before = $this->grid();
            foreach ($grid as $origin => $row) {
                foreach ($row as $destination => $level) {
                    DB::table('pr_shipping_route_levels')->updateOrInsert(['origin_region' => $origin, 'destination_region' => $destination],
                        ['level' => $level, 'updated_at' => now()]);
                }
            }
            AuditLog::record(null, 'shipping_route_levels.updated', ['grid' => $before], ['grid' => $grid, 'from' => $from]);

            return $this->generate($from, updateGeneric: true);
        });
    }

    /**
     * Save new level prices and generate the route prices from $from.
     *
     * @param  array<int, array{fees: array<string, string>, per_kg: string}>  $levels  level => tier kg => fee, per-kg fee
     */
    public function updateLevels(array $levels, string $from): array
    {
        return DB::transaction(function () use ($levels, $from) {
            $before = $this->levels();
            foreach ($levels as $level => $prices) {
                abort_unless(isset($before[$level]), 422, "Level {$level} does not exist.");
                $merged = ['fees' => array_replace($before[$level]['fees'], array_map(fn ($fee) => self::money($fee), $prices['fees'] ?? [])),
                    'per_kg' => self::money($prices['per_kg'] ?? $before[$level]['per_kg']), 'over_kg' => $before[$level]['over_kg']];
                self::validateLevels([$level => $merged]);
                DB::table('pr_shipping_levels')->where('level', $level)->update(['per_kg_fee' => $merged['per_kg'], 'updated_at' => now()]);
                foreach ($merged['fees'] as $kg => $fee) {
                    DB::table('pr_shipping_level_fees')->where('level', $level)->where('max_weight_kg', $kg)->update(['fee' => $fee]);
                }
            }
            AuditLog::record(null, 'shipping_levels.updated', ['levels' => $before], ['levels' => $this->levels(), 'from' => $from]);

            return $this->generate($from, updateGeneric: true);
        });
    }

    /** @return array<int, array{name: string, fees: array<string, string>, per_kg: string, over_kg: string}> level => prices */
    public function levels(): array
    {
        $fees = DB::table('pr_shipping_level_fees')->orderBy('max_weight_kg')->get()->groupBy('level');

        return DB::table('pr_shipping_levels')->orderBy('level')->get()->mapWithKeys(fn ($row) => [(int) $row->level => [
            'name' => $row->name,
            'fees' => ($fees[$row->level] ?? collect())->mapWithKeys(fn ($fee) => [self::money($fee->max_weight_kg) => self::money($fee->fee)])->all(),
            'per_kg' => self::money($row->per_kg_fee),
            'over_kg' => self::money($row->over_kg),
        ]])->all();
    }

    /** @return array<string, array<string, int>> origin => destination => level */
    public function grid(): array
    {
        $grid = [];
        foreach (DB::table('pr_shipping_route_levels')->get() as $row) {
            $grid[$row->origin_region][$row->destination_region] = (int) $row->level;
        }

        return $grid;
    }

    /** Every level has the same tiers, prices strictly increase with weight, and all exceed the minimum rider pay. */
    public static function validateLevels(array $levels): void
    {
        $minimum = self::money(config('pickrush.shipping.min_rider_pay'));
        $tiers = null;
        foreach ($levels as $level => $prices) {
            $keys = array_keys($prices['fees']);
            sort($keys, SORT_NUMERIC);
            if ($keys === [] || ($tiers !== null && $keys !== $tiers)) {
                throw new OperationRefused("Level {$level} must have the same weight tiers as the other levels.");
            }
            $tiers ??= $keys;
            $previous = null;
            foreach ($keys as $kg) {
                $fee = self::money($prices['fees'][$kg]);
                if (bccomp($fee, $minimum, 2) <= 0) {
                    throw new OperationRefused("Level {$level} up to {$kg} kg: ₱{$fee} must be more than the ₱{$minimum} minimum rider pay.");
                }
                if ($previous !== null && bccomp($fee, $previous, 2) <= 0) {
                    throw new OperationRefused("Level {$level}: the {$kg} kg price (₱{$fee}) must be higher than the lighter tier (₱{$previous}).");
                }
                $previous = $fee;
            }
            if (bccomp(self::money($prices['per_kg']), '0', 2) <= 0) {
                throw new OperationRefused("Level {$level}: the per-kg rate above the heaviest tier must be more than ₱0.");
            }
        }
    }

    /** Every route has a known level, and A → B is the same level as B → A. */
    public static function validateGrid(array $grid, array $regions, array $levelIds): void
    {
        foreach ($regions as $origin) {
            foreach ($regions as $destination) {
                $level = $grid[$origin][$destination] ?? null;
                if ($level === null || ! in_array($level, $levelIds, true)) {
                    throw new OperationRefused("Pick a price level for {$origin} → {$destination}.");
                }
                if (($grid[$destination][$origin] ?? null) !== $level) {
                    throw new OperationRefused("{$origin} → {$destination} is level {$level} but {$destination} → {$origin} is level "
                        .($grid[$destination][$origin] ?? '?').'. A route costs the same both ways.');
                }
            }
        }
    }

    /** The generic rows must exist on $from and, unless they are being moved too, equal the NCR-origin prices. */
    private function checkBase(array $regions, array $levels, array $grid, string $from, bool $updateGeneric): void
    {
        foreach ($regions as $destination) {
            $expected = $levels[$grid[self::ORIGIN_OF_GENERIC][$destination]];
            $generic = DB::table('pr_shipping_rates')->whereNull('origin_region')->where('destination_region', $destination)
                ->whereDate('effective_from', '<=', $from)->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->get()->mapWithKeys(fn ($row) => [self::money($row->max_weight_kg) => self::money($row->fee)])->all();
            $perKg = DB::table('pr_shipping_overweight_rates')->whereNull('origin_region')->where('destination_region', $destination)
                ->whereDate('effective_from', '<=', $from)->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->value('per_kg_fee');

            if (array_diff_key($expected['fees'], $generic) !== [] || $perKg === null) {
                throw new OperationRefused("The generic {$destination} rates in force on {$from} are missing; load them before generating route prices.");
            }
            if (! $updateGeneric) {
                ksort($generic, SORT_NUMERIC);
                $wanted = $expected['fees'];
                ksort($wanted, SORT_NUMERIC);
                if (array_intersect_key($generic, $wanted) !== $wanted || bccomp(self::money($perKg), $expected['per_kg'], 2) !== 0) {
                    throw new OperationRefused("The generic {$destination} rates differ from the NCR → {$destination} route prices; they must be the same price list.");
                }
            }
        }
    }

    /**
     * Make one route's row carry $value from $from: unchanged if it already does; a row scheduled
     * to start on or after $from is corrected in place; otherwise the row in force ends the day
     * before and a new one starts.
     */
    private function sync(string $table, array $key, string $column, ?string $origin, string $destination, string $value,
        string $from, array &$counts, array $extra = []): void
    {
        $rows = DB::table($table)->where($key)->where('destination_region', $destination)
            ->when($origin, fn ($q) => $q->where('origin_region', $origin), fn ($q) => $q->whereNull('origin_region'))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
            ->orderBy('effective_from')->get();
        $scheduled = $rows->first(fn ($row) => substr((string) $row->effective_from, 0, 10) >= $from);
        $current = $rows->last(fn ($row) => substr((string) $row->effective_from, 0, 10) < $from);

        if ($scheduled) {
            if (bccomp(self::money($scheduled->{$column}), $value, 2) === 0) {
                $counts['unchanged']++;
            } else {
                DB::table($table)->where('id', $scheduled->id)->update([$column => $value, 'updated_at' => now()] + $extra);
                $counts['updated']++;
            }

            return;
        }
        if ($current && bccomp(self::money($current->{$column}), $value, 2) === 0) {
            $counts['unchanged']++;

            return;
        }
        if ($current) {
            DB::table($table)->where('id', $current->id)->update(['effective_to' => Carbon::parse($from)->subDay()->toDateString(), 'updated_at' => now()]);
            $counts['ended']++;
        }
        DB::table($table)->insert($key + $extra + [
            'origin_region' => $origin, 'destination_region' => $destination, $column => $value,
            'effective_from' => $from, 'effective_to' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $counts['created']++;
    }

    /** A 2-decimal string (database decimals and form input are already decimal strings). */
    private static function money(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new OperationRefused("\"{$value}\" is not a valid peso amount.");
        }

        return bcadd($value, '0', 2);
    }
}
