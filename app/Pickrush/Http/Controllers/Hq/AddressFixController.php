<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Models\Geo\Barangay;
use App\Models\Geo\Municipality;
use App\Models\Geo\Province;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\Hq\AddressFixer;
use Illuminate\Http\Request;

/** Queue of parcels whose address matched no station, with a picker to set the right barangay. */
class AddressFixController extends PortalController
{
    public function index(Request $request)
    {
        $parcels = Parcel::with('originHub', 'destHub', 'currentHub')->where('needs_address_fix', true)
            ->whereNotIn('current_status', [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost])
            ->oldest()->paginate(30);

        // Cascading picker without JavaScript: province → city → barangay through query parameters.
        $province = $request->query('province');
        $municipality = $request->query('municipality');

        return view('pickrush.hq.address-fix', [
            'parcels' => $parcels,
            'selected' => $request->query('parcel'),
            'provinces' => Province::orderBy('name')->get(),
            'municipalities' => $province ? Municipality::where('province_code', $province)->orderBy('name')->get() : collect(),
            'barangays' => $municipality ? Barangay::where('municipality_code', $municipality)->orderBy('name')->get() : collect(),
            'province' => $province,
            'municipality' => $municipality,
        ]);
    }

    public function update(Request $request, Parcel $parcel, AddressFixer $fixer)
    {
        $data = $request->validate([
            'side' => 'required|in:pickup,delivery',
            'barangay_code' => 'required|string|size:9',
            'address_line' => 'nullable|string|max:255',
        ]);

        return $this->attempt(fn () => $fixer->fix($parcel, $data['side'], $data['barangay_code'], $data['address_line'] ?? null, $this->staff()),
            "{$parcel->tracking_no} now routes to its station.");
    }
}
