<?php

namespace App\Pickrush\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Data-layer hub scoping for records that belong to one hub (riders, zones, remittances): hub
 * staff only ever load their own hub's rows, so another hub's id is a 404. HQ and the system see
 * all. The model names its hub column in $hubColumn (default hub_id).
 */
trait HubOwned
{
    protected static function bootHubOwned(): void
    {
        static::addGlobalScope('hub', function (Builder $query) {
            $staff = Actor::staff();
            if ($staff && ! $staff->isCompany()) {
                $model = $query->getModel();
                $query->where($model->getTable().'.'.$model->hubColumn(), $staff->hub_id);
            }
        });
    }

    public function hubColumn(): string
    {
        return property_exists($this, 'hubColumn') ? $this->hubColumn : 'hub_id';
    }
}
