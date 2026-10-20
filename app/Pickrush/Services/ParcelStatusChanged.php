<?php

namespace App\Pickrush\Services;

use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after a parcel event is committed. The Picksell bridge listens to keep orders in step. */
class ParcelStatusChanged
{
    use Dispatchable;

    public function __construct(public Parcel $parcel, public ParcelEvent $event) {}
}
