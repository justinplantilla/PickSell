<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/** A company truck or van kept at a hub for trips, with the driver who usually takes it. */
class Vehicle extends Model
{
    use Audited, HubOwned;

    protected $table = 'pr_vehicles';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function defaultDriver()
    {
        return $this->belongsTo(Driver::class, 'default_driver_id');
    }

    /** How many active vehicles a hub may keep: a station runs one, a province hub up to three. */
    public static function limitFor(Hub $hub): int
    {
        return (int) config('pickrush.fleet.max_vehicles.'.$hub->type->value, 1);
    }
}
