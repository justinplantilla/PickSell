<?php

namespace App\Services;

use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use Illuminate\Http\Request;

/**
 * The door Pickrush riders navigate to for the buyer's default (profile) address. Saving it also
 * moves the pin of the buyer's parcels still on their way there. Used by the website's account
 * page and the buyer app (PATCH /api/mobile/buyer/delivery-location).
 */
class DeliveryPin
{
    /** @return int parcels whose pin moved */
    public function save(User $buyer, Request $request): int
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:4,22',      // the Philippines
            'lng' => 'required|numeric|between:116,127',
        ], ['lat.required' => 'Drop a pin on the map first.', 'lat.between' => 'Pin a spot inside the Philippines.', 'lng.between' => 'Pin a spot inside the Philippines.']);
        $buyer->update(['delivery_lat' => $data['lat'], 'delivery_lng' => $data['lng']]);

        return PicksellBridge::buyerPinned($buyer);
    }
}
