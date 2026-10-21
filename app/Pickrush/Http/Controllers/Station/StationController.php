<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\Hub;

abstract class StationController extends PortalController
{
    protected function station(): Hub
    {
        return $this->hub();
    }
}
