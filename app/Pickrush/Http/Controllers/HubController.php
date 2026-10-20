<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Enums\HubType;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** HQ hub directory: every province and station hub, with who runs it. Editing comes with the HQ portal. */
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
