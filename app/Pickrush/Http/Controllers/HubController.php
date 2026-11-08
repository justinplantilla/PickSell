<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Geo\Municipality;
use App\Models\Geo\Province;
use App\Pickrush\Enums\HubType;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Services\Hq\HubCreator;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Support\Actor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** HQ hub directory: every province and station hub of the company, with who runs it. Super admins open new hubs. */
class HubController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => in_array($request->query('type'), ['province', 'station'], true) ? $request->query('type') : 'all',
            'readiness' => $request->query('readiness') === 'no_manager' ? 'no_manager' : 'all',
        ];
        $realManager = fn (Builder $q) => $q->where('role', StaffRole::HubManager)->where('is_active', true)->where('is_sample', false);

        $hubs = Hub::with('parent')
            ->withExists(['staff as has_manager' => $realManager])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$filters['q']}%")
                ->orWhere('province_name', 'like', "%{$filters['q']}%")
                ->orWhere('code', 'like', "%{$filters['q']}%")))
            ->when($filters['type'] !== 'all', fn ($q) => $q->where('type', HubType::from($filters['type'])))
            ->when($filters['readiness'] === 'no_manager', fn ($q) => $q->whereDoesntHave('staff', $realManager))
            ->orderBy('type')->orderBy('province_name')->orderBy('name')
            ->paginate(50)->withQueryString();

        return view('pickrush.hubs.index', compact('hubs', 'filters'));
    }

    public function create()
    {
        $this->authorizeSuperAdmin();
        $provinceHubs = Hub::provinces()->orderBy('name')->get();
        $taken = Hub::stations()->pluck('psgc_code')->flip();

        return view('pickrush.hubs.create', [
            // Provinces (and Metro Manila districts) without a province hub of this company yet.
            'provinces' => Province::whereNotIn('code', $provinceHubs->pluck('psgc_code'))->orderBy('name')->get(),
            // Cities and municipalities under the company's province hubs, without a station yet.
            'stationChoices' => $provinceHubs->mapWithKeys(fn (Hub $hub) => [$hub->name => Municipality::with('province')->where('province_code', $hub->psgc_code)
                ->orderBy('name')->get()->reject(fn ($m) => $taken->has($m->code))]),
            'isDefault' => (bool) Actor::staff()->company?->is_default,
        ]);
    }

    public function store(Request $request, HubCreator $hubs)
    {
        $this->authorizeSuperAdmin();
        $data = $request->validate([
            'type' => 'required|in:province,station',
            'province_code' => 'required_if:type,province|nullable|string',
            'municipality_code' => 'required_if:type,station|nullable|string',
            'name' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:4,22|required_with:lng',     // the Philippines
            'lng' => 'nullable|numeric|between:116,127|required_with:lat',
        ], ['lat.between' => 'Drop the pin inside the Philippines.', 'lng.between' => 'Drop the pin inside the Philippines.', 'province_code.required_if' => 'Pick the province.', 'municipality_code.required_if' => 'Pick the city or municipality.']);
        $companyId = Actor::staff()->company_id;

        try {
            $details = array_intersect_key($data, array_flip(['name', 'address', 'lat', 'lng']));
            $hub = $data['type'] === 'province'
                ? $hubs->province($companyId, $data['province_code'], $details)
                : $hubs->station($companyId, $data['municipality_code'], $details);
        } catch (OperationRefused $e) {
            return back()->withInput()->withErrors(['hub' => $e->getMessage()]);
        }

        return redirect()->route('pickrush.hubs.show', $hub)->with('success', "{$hub->name} is open. Post a hub manager to it next"
            .($hub->isProvince() ? ', and link it to neighboring province hubs.' : '.'));
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(Actor::staff()->role === StaffRole::SuperAdmin, 403, 'Only a super admin opens hubs.');
    }

    public function update(\Illuminate\Http\Request $request, Hub $hub)
    {
        abort_unless(\App\Pickrush\Support\Actor::staff()->role === StaffRole::SuperAdmin, 403, 'Only a super admin edits hubs.');
        $data = $request->validate([
            'address' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:4,22',     // the Philippines
            'lng' => 'nullable|numeric|between:116,127',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        if (! $data['is_active'] && $hub->isProvince() && $hub->childStations()->where('is_active', true)->exists()) {
            return back()->withErrors(['operation' => 'Deactivate its stations first, or keep the province hub active.']);
        }
        $hub->update($data);

        return back()->with('success', $data['is_active'] ? "{$hub->name} saved." : "{$hub->name} is inactive: parcels routed to it go to the exception bin.");
    }

    public function show(Hub $hub)
    {
        $hub->load(['parent', 'staff' => fn ($q) => $q->orderBy('role')->orderBy('full_name')]);
        $stations = $hub->isProvince() ? $hub->childStations()->orderBy('name')->get() : collect();

        return view('pickrush.hubs.show', [
            'hub' => $hub,
            'stations' => $stations,
            'gaps' => $hub->readinessGaps(),
        ]);
    }
}
