<?php

namespace App\Pickrush\Http\Controllers\Province;

use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Services\Province\Inventory;
use Illuminate\Http\Request;

/** Everything on the floor, longest-waiting first, with aging alerts. */
class InventoryController extends PortalController
{
    public function __invoke(Request $request, Inventory $inventory)
    {
        $onHand = $inventory->onHand($this->hub());
        $aging = $request->boolean('aging');

        return view('pickrush.province.inventory', [
            'parcels' => $aging ? $onHand->filter(fn ($p) => $p->dwell_hours >= Inventory::AGING_HOURS)->values() : $onHand,
            'total' => $onHand->count(),
            'agingCount' => $onHand->filter(fn ($p) => $p->dwell_hours >= Inventory::AGING_HOURS)->count(),
            'agingHours' => Inventory::AGING_HOURS,
            'aging' => $aging,
        ]);
    }
}
