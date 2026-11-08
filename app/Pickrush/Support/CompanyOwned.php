<?php

namespace App\Pickrush\Support;

use App\Pickrush\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Data-layer company scoping: staff and riders only ever load their own company's rows, so another
 * company's id is a 404. The system (bridge, console, marketplace admin) sees all.
 *
 * A model either has its own company_id column, or belongs to a company through a parent row and
 * names it in $companyThrough as [column => parent table], e.g. ['station_hub_id' => 'pr_hubs'].
 * A new row with its own column takes the acting company, else inferCompanyId(), else Pickrush.
 */
trait CompanyOwned
{
    protected static function bootCompanyOwned(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $companyId = Actor::companyId();
            if ($companyId !== null) {
                $query->getModel()->scopeToCompany($query, $companyId);
            }
        });

        static::creating(function (Model $model) {
            if ($model->companyThrough() === null && ! $model->company_id) {
                $model->company_id = Actor::companyId() ?? $model->inferCompanyId() ?? Company::defaultId();
            }
        });
    }

    /** Limits $query to one company, for code that runs outside a staff or rider request. */
    public function scopeOfCompany(Builder $query, int $companyId): Builder
    {
        return $this->scopeToCompany($query->withoutGlobalScope('company'), $companyId);
    }

    public function scopeToCompany(Builder $query, int $companyId): Builder
    {
        $table = $this->getTable();
        if ($through = $this->companyThrough()) {
            [$column, $parent] = [array_key_first($through), reset($through)];

            return $query->whereIn("{$table}.{$column}", DB::table($parent)->select('id')->where('company_id', $companyId));
        }

        return $query->where("{$table}.company_id", $companyId);
    }

    /** @return array<string, string>|null [column => parent table] when the company comes from a parent row */
    public function companyThrough(): ?array
    {
        return property_exists($this, 'companyThrough') ? $this->companyThrough : null;
    }

    /** The company of this row, for audit entries. */
    public function owningCompanyId(): ?int
    {
        if ($through = $this->companyThrough()) {
            $parentId = $this->getAttribute(array_key_first($through));

            return $parentId ? DB::table(reset($through))->where('id', $parentId)->value('company_id') : null;
        }

        return $this->company_id;
    }

    /** The company a new row belongs to when nobody is acting for one (e.g. from its hub). */
    protected function inferCompanyId(): ?int
    {
        return null;
    }
}
