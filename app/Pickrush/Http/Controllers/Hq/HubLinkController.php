<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubLink;
use App\Pickrush\Services\HubRouter;
use App\Pickrush\Support\Actor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Province links: which province hubs are neighbors (by land or ferry) and how long the trip
 * takes. Province-to-province trips only run along an active link; parcels relay along the
 * fastest chain. HQ roles can look; a super admin adds links, edits hours or turns them off.
 */
class HubLinkController extends Controller
{
    public function index(Request $request, HubRouter $router)
    {
        $q = trim((string) $request->query('q', ''));
        $links = HubLink::with('hubA', 'hubB')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereHas('hubA', fn ($h) => $h->where('name', 'like', "%{$q}%"))
                ->orWhereHas('hubB', fn ($h) => $h->where('name', 'like', "%{$q}%"))))
            ->get()->sortBy(fn (HubLink $link) => $link->hubA->name.' '.$link->hubB->name)->values();

        return view('pickrush.hq.links', [
            'links' => $links,
            'q' => $q,
            'provinces' => Hub::provinces()->orderBy('name')->get(),
            'disconnected' => Hub::whereIn('id', $router->disconnected())->orderBy('name')->get(),
            'canEdit' => $this->canEdit(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->canEdit(), 403, 'Only a super admin edits province links.');
        $province = Rule::exists('pr_hubs', 'id')->where('type', 'province');
        $data = $request->validate([
            'hub_one' => ['required', 'integer', $province],
            'hub_other' => ['required', 'integer', 'different:hub_one', $province],
            'mode' => ['required', Rule::in(array_keys(HubLink::MODES))],
            'travel_hours' => 'required|numeric|min:0.5|max:200',
        ], ['hub_other.different' => 'Pick two different province hubs.']);
        [$a, $b] = HubLink::pair((int) $data['hub_one'], (int) $data['hub_other']);
        if (HubLink::where('hub_a_id', $a)->where('hub_b_id', $b)->exists()) {
            return back()->withErrors(['operation' => 'These hubs are already linked. Edit the link below instead.'])->withInput();
        }

        $link = HubLink::create(['hub_a_id' => $a, 'hub_b_id' => $b, 'mode' => $data['mode'], 'travel_hours' => $data['travel_hours'], 'is_active' => true]);

        return back()->with('success', "{$link->hubA->name} and {$link->hubB->name} are now linked.");
    }

    public function update(Request $request, HubLink $link)
    {
        abort_unless($this->canEdit(), 403, 'Only a super admin edits province links.');
        $data = $request->validate([
            'mode' => ['required', Rule::in(array_keys(HubLink::MODES))],
            'travel_hours' => 'required|numeric|min:0.5|max:200',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $link->update($data);

        return back()->with('success', "{$link->hubA->name} – {$link->hubB->name}: ".($link->is_active ? 'saved.' : 'turned off. Parcels are routed around it from now on.'));
    }

    private function canEdit(): bool
    {
        return Actor::staff()->role === StaffRole::SuperAdmin;
    }
}
