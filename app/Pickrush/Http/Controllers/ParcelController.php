<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\HubRouter;
use App\Pickrush\Services\Labels;
use App\Pickrush\Services\ManifestService;
use Illuminate\Http\Request;

/** Look up any parcel the viewer may see (hub staff: their hub's; HQ: all) with its full scan history. */
class ParcelController extends Controller
{
    public function index(Request $request)
    {
        $q = ManifestService::normalize((string) $request->query('q', ''));
        if ($q !== '' && $parcel = Parcel::where('tracking_no', $q)->first()) {
            return redirect()->route('pickrush.parcels.show', $parcel);
        }

        return view('pickrush.parcels.index', [
            'q' => $q,
            'parcels' => Parcel::with('originHub', 'destHub', 'currentHub')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('tracking_no', 'like', "%{$q}%")->orWhere('consignee_name', 'like', "%{$request->query('q')}%")))
                ->latest('updated_at')->paginate(40)->withQueryString(),
        ]);
    }

    public function show(Parcel $parcel, HubRouter $router)
    {
        $parcel->load('originHub', 'destHub', 'currentHub', 'nextHub', 'events.hub', 'events.toHub', 'assignments.rider');

        // The hubs still ahead, as routing stands now (it is worked out again at every hub).
        $from = $parcel->currentHub ?? $parcel->nextHub;
        $target = $parcel->is_return ? $parcel->originHub : $parcel->destHub;
        $ended = in_array($parcel->current_status, [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost, ParcelStatus::Abandoned], true);

        return view('pickrush.parcels.show', [
            'parcel' => $parcel,
            'plan' => $from && $target && ! $ended ? $router->path($from, $target) : null,
            'planned' => $from && $target && ! $ended,
        ]);
    }
}
