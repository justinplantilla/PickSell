<?php

namespace App\Pickrush\Services\Hq;

use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Staff;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * HQ-editable rates and policies. Defaults live in config/pickrush.php; a saved value in
 * pr_settings overrides its config key at boot, so every part of Pickrush reads the same
 * config('pickrush.…') value whether or not HQ has changed it.
 */
class PolicySettings
{
    /** key (under config "pickrush.") => [group, label, validation, help] */
    public const FIELDS = [
        'policies.max_delivery_attempts' => ['Delivery', 'Delivery attempts before return', 'required|integer|min:1|max:10', 'After this many failed attempts the parcel must go back to the seller.'],
        'policies.aging_hours' => ['Delivery', 'Aging after (hours on a hub floor)', 'required|integer|min:1|max:720', 'Inventory and dashboards flag parcels waiting longer.'],
        'policies.stuck_after_hours' => ['Delivery', 'Stuck after (hours with no scan)', 'required|integer|min:1|max:720', 'Raises a stuck-parcel alert.'],
        'policies.sla_success_rate' => ['Delivery', 'Hub success-rate target (%)', 'required|numeric|min:1|max:100', 'Hub ranking colours hubs below this.'],
        'policies.rider_limits.probation.cod_limit' => ['Riders', 'Probation COD limit (₱)', 'required|numeric|min:0|max:500000', 'New riders start with this cash cap.'],
        'policies.rider_limits.probation.max_parcels' => ['Riders', 'Probation parcels a day', 'required|integer|min:1|max:300', null],
        'policies.rider_limits.standard.cod_limit' => ['Riders', 'Standard COD limit (₱)', 'required|numeric|min:0|max:500000', 'Suggested once probation ends.'],
        'policies.rider_limits.standard.max_parcels' => ['Riders', 'Standard parcels a day', 'required|integer|min:1|max:300', null],
        'policies.two_person_count_above' => ['Cash', 'Second counter needed above (₱)', 'required|numeric|min:0|max:10000000', 'Rider cash counts above this need two staff.'],
        'policies.remittance_cycle' => ['Cash', 'Station remittance cycle', 'required|in:daily,every_two_days,weekly', null],
        'policies.payout_release_days' => ['Cash', 'Seller payout window (days after delivery)', 'required|integer|min:0|max:60', 'The dispute window before COD is released to the seller.'],
        'rates.same_city.base' => ['Rates', 'Same city: base fee (₱)', 'required|numeric|min:0|max:100000', null],
        'rates.same_city.included_kg' => ['Rates', 'Same city: kg included', 'required|numeric|min:0|max:1000', null],
        'rates.same_city.per_kg' => ['Rates', 'Same city: each extra kg (₱)', 'required|numeric|min:0|max:100000', null],
        'rates.same_province.base' => ['Rates', 'Same province: base fee (₱)', 'required|numeric|min:0|max:100000', null],
        'rates.same_province.included_kg' => ['Rates', 'Same province: kg included', 'required|numeric|min:0|max:1000', null],
        'rates.same_province.per_kg' => ['Rates', 'Same province: each extra kg (₱)', 'required|numeric|min:0|max:100000', null],
        'rates.inter_province.base' => ['Rates', 'Inter-province: base fee (₱)', 'required|numeric|min:0|max:100000', null],
        'rates.inter_province.included_kg' => ['Rates', 'Inter-province: kg included', 'required|numeric|min:0|max:1000', null],
        'rates.inter_province.per_kg' => ['Rates', 'Inter-province: each extra kg (₱)', 'required|numeric|min:0|max:100000', null],
        'rates.cod_fee_percent' => ['Rates', 'COD fee (% of the COD amount)', 'required|numeric|min:0|max:100', null],
    ];

    /** Apply saved values over the config defaults. Safe before the table exists. */
    public static function load(): void
    {
        try {
            foreach (DB::table('pr_settings')->pluck('value', 'key') as $key => $value) {
                if (array_key_exists($key, self::FIELDS)) {
                    config(["pickrush.{$key}" => json_decode($value, true)]);
                }
            }
        } catch (Throwable) {
            // No database yet (fresh install, config:cache in CI): defaults stand.
        }
    }

    public static function value(string $key): mixed
    {
        return config("pickrush.{$key}");
    }

    /** @param array<string, mixed> $values  key => new value (already validated) */
    public function save(array $values, Staff $admin): int
    {
        $changed = 0;
        DB::transaction(function () use ($values, $admin, &$changed) {
            foreach ($values as $key => $value) {
                if (! array_key_exists($key, self::FIELDS)) {
                    continue;
                }
                $value = is_numeric($value) ? $value + 0 : $value;
                $before = self::value($key);
                if ($before == $value) {
                    continue;
                }
                DB::table('pr_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_by' => $admin->id, 'updated_at' => now()]);
                config(["pickrush.{$key}" => $value]);
                AuditLog::record(null, 'settings.changed', [$key => $before], [$key => $value]);
                $changed++;
            }
        });

        return $changed;
    }
}
