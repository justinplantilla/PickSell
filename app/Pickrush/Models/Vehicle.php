<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/** A truck or van a hub uses for trips, with its usual driver. */
class Vehicle extends Model
{
    use Audited, HubOwned;

    protected $table = 'pr_vehicles';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
