<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Parcel;
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

    /** The 4×6 label (barcode + QR) to print or reprint. */
    public function label(Parcel $parcel, Labels $labels)
    {
        \App\Pickrush\Models\AuditLog::record($parcel, 'label_printed');

        return $labels->parcel($parcel);
    }

    public function show(Parcel $parcel)
    {
        $parcel->load('originHub', 'destHub', 'currentHub', 'nextHub', 'events.hub', 'events.toHub', 'assignments.rider');

        return view('pickrush.parcels.show', ['parcel' => $parcel]);
    }
}
