<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Zone;
use App\Pickrush\Services\Station\ZoneSetup;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZoneController extends StationController
{
    public function index()
    {
        $barangays = ZoneSetup::barangaysOf($this->staff());

        return view('pickrush.station.zones', [
            'zones' => Zone::with('defaultRider', 'backupRider')->withCount('barangayLinks')->orderBy('name')->get(),
            'unzoned' => $barangays->whereNull('zone')->values(),
            'barangayTotal' => $barangays->count(),
        ]);
    }

    public function create()
    {
        return $this->form(new Zone);
    }

    public function edit(Zone $zone)
    {
        return $this->form($zone);
    }

    public function store(Request $request, ZoneSetup $setup)
    {
        return $this->save($request, $setup, null);
    }

    public function update(Request $request, ZoneSetup $setup, Zone $zone)
    {
        return $this->save($request, $setup, $zone);
    }

    private function form(Zone $zone)
    {
        return view('pickrush.station.zone-form', [
            'zone' => $zone,
            'barangays' => ZoneSetup::barangaysOf($this->staff()),
            'selected' => $zone->exists ? $zone->barangayLinks()->pluck('barangay_code')->all() : [],
            'riders' => Rider::where('station_hub_id', $this->station()->id)->whereIn('status', ['active', 'under_review', 'pending'])->orderBy('full_name')->get(),
        ]);
    }

    private function save(Request $request, ZoneSetup $setup, ?Zone $zone)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('pr_zones', 'name')->where('station_hub_id', $this->station()->id)->ignore($zone?->id)],
            'default_rider_id' => 'nullable|integer',
            'backup_rider_id' => 'nullable|integer',
            'max_parcels' => 'nullable|integer|min:1|max:500',
            'barangays' => 'array',
            'barangays.*' => 'string|size:9',
        ]);

        try {
            $zone = $setup->save($zone, $data, $data['barangays'] ?? [], $this->staff());
        } catch (DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pickrush.station.zones.index')->with('success', "{$zone->name} saved.");
    }
}
