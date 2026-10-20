<?php

namespace App\Pickrush\Http\Controllers\Province;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Services\Province\Inventory;
use App\Pickrush\Services\Province\Sorting;
use DomainException;
use Illuminate\Http\Request;

/** Scan a parcel, see its bin. Bins are the next hubs: this province's stations and other province hubs. */
class SortingController extends PortalController
{
    public function index(Inventory $inventory)
    {
        $onHand = $inventory->onHand($this->hub());
        $bins = $onHand->where('current_status', ParcelStatus::AtProvinceHub)->groupBy('next_hub_id')
            ->map(fn ($parcels) => (object) [
                'hub' => $parcels->first()->nextHub,
                'bin' => $parcels->first()->nextHub ? Sorting::binFor($parcels->first()->nextHub) : Sorting::EXCEPTION_BIN,
                'sorted' => $parcels->whereNotNull('sorted_at')->count(),
                'total' => $parcels->count(),
            ])->sortBy('bin')->values();

        return view('pickrush.province.sorting', [
            'result' => session('sort_result'),
            'bins' => $bins,
            'exceptionBin' => $onHand->where('current_status', ParcelStatus::OnHold)->count(),
        ]);
    }

    public function scan(Request $request, Sorting $sorting)
    {
        $data = $request->validate(['tracking_no' => 'required|string|max:30']);

        try {
            $result = $sorting->sort($data['tracking_no'], $this->staff());
        } catch (DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->with('sort_result', ['bin' => Sorting::EXCEPTION_BIN, 'tracking_no' => strtoupper($data['tracking_no']), 'error' => true]);
        }

        return back()->with('sort_result', [
            'bin' => $result['bin'],
            'tracking_no' => $result['parcel']->tracking_no,
            'destination' => $result['destination']?->name,
            'received' => $result['received'],
            'error' => $result['exception'],
        ]);
    }
}
