<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\TrackingNumber;
use Illuminate\Http\Request;

/**
 * Public tracking by tracking number, for buyers, sellers and shippers outside Picksell.
 * Shows only the route and times: no names, phone numbers or addresses.
 */
class TrackingController extends Controller
{
    public function __invoke(Request $request)
    {
        $number = strtoupper(preg_replace('/\s+/', '', (string) $request->query('no', '')));
        $parcel = null;
        $error = null;

        if ($number !== '') {
            $parcel = TrackingNumber::isValid($number)
                ? Parcel::withoutGlobalScopes()->with('events.hub', 'events.toHub', 'destHub')->where('tracking_no', $number)->first()
                : null;
            $error = $parcel ? null : 'We could not find that tracking number. Check it against your waybill and try again.';
        }

        return view('pickrush.track', [
            'number' => $number,
            'parcel' => $parcel,
            'events' => $parcel?->events->reverse()->values() ?? collect(),
            'error' => $error,
        ]);
    }
}
