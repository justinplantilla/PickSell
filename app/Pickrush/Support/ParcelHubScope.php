<?php

namespace App\Pickrush\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Data-layer hub scoping: hub staff only ever see parcels that are at, heading to, or belong to
 * their hub, on every query, so a guessed id from another hub is a 404. HQ staff and the system
 * (bridge, console) see everything. Bypass deliberately with Parcel::withoutGlobalScope(...).
 */
class ParcelHubScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $staff = Actor::staff();
        if (! $staff || $staff->isCompany()) {
            return;
        }

        $hubId = $staff->hub_id;
        $table = $model->getTable();
        $builder->where(fn (Builder $q) => $q
            ->where("{$table}.current_hub_id", $hubId)
            ->orWhere("{$table}.next_hub_id", $hubId)
            ->orWhere("{$table}.origin_hub_id", $hubId)
            ->orWhere("{$table}.dest_hub_id", $hubId));
    }
}
