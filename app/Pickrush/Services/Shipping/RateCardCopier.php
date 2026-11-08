<?php

namespace App\Pickrush\Services\Shipping;

use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Company;
use App\Pickrush\Support\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Gives a newly approved company its own rate card: a copy of Pickrush's price levels, route
 * grid and the rate rows in force today or later, which the company then edits as its own.
 * Does nothing for a company that already has a card.
 */
class RateCardCopier
{
    public function copy(Company $company): bool
    {
        $source = Company::defaultId();
        if ($company->id === $source || DB::table('pr_shipping_levels')->where('company_id', $company->id)->exists()) {
            return false;
        }

        DB::transaction(function () use ($company, $source) {
            $today = now()->toDateString();
            $now = now();

            foreach (['pr_shipping_levels', 'pr_shipping_level_fees', 'pr_shipping_route_levels'] as $table) {
                $rows = DB::table($table)->where('company_id', $source)->get()
                    ->map(fn ($row) => ['company_id' => $company->id] + (array) $row)->all();
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }

            foreach (['pr_shipping_rates', 'pr_shipping_overweight_rates'] as $table) {
                $rows = DB::table($table)->where('company_id', $source)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
                    ->get()
                    ->map(fn ($row) => array_merge((array) $row, ['company_id' => $company->id, 'created_at' => $now, 'updated_at' => $now]))
                    ->map(fn ($row) => array_diff_key($row, ['id' => true]))
                    ->all();
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }

            AuditLog::record($company, 'rate_card_copied', [], ['from_company' => $source], Actor::picksell());
        });

        return true;
    }
}
