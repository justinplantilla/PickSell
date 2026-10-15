<?php

namespace App\Http\Controllers;

use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\Order;
use App\Services\BarangayImporter;
use App\Services\HubOperatorService;
use App\Services\LogisticsService;
use App\Support\LogisticsDomains;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Province Hub Portal
 *
 * Handles operations scoped to a single province hub (sorting center):
 *   - View parcels arriving from / departing to station hubs
 *   - Receive linehaul from another province hub
 *   - Dispatch inter-province linehaul to destination province hub
 *   - Manage station hubs under this province hub
 */
class ProvinceHubController extends Controller
{
    private function hub(): LogisticsBranch
    {
        return request()->attributes->get('province_hub');
    }

    private function ordersForHub()
    {
        $hub = $this->hub();

        return Order::query()
            ->where('logistics_id', $hub->logistics_id)
            ->where(fn ($q) => $q
                ->where('origin_hub_id', $hub->id)
                ->orWhere('destination_hub_id', $hub->id));
    }

    /**
     * Linehaul queues as seen from this hub. Each takes and returns a query builder.
     *
     * @return array<string, callable>
     */
    private function queues(): array
    {
        $hubId = $this->hub()->id;
        $interProvince = fn ($q) => $q->whereNotNull('origin_hub_id')->whereNotNull('destination_hub_id')
            ->whereColumn('origin_hub_id', '!=', 'destination_hub_id');

        return [
            'incoming_linehaul' => fn ($q) => $q->where('status', 'in_transit')->where('destination_hub_id', $hubId),
            'ready_to_dispatch' => fn ($q) => $q->where('status', 'sorted')->where('origin_hub_id', $hubId)
                ->where($interProvince)->whereNull('destination_hub_received_at'),
            'outbound_linehaul' => fn ($q) => $q->where('status', 'in_transit')->where('origin_hub_id', $hubId),
            'received_here' => fn ($q) => $q->where('destination_hub_id', $hubId)->whereNotNull('destination_hub_received_at')
                ->whereIn('status', ['at_sorting_center', 'sorted']),
            'delivery_failed' => fn ($q) => $q->where('status', 'delivery_failed'),
        ];
    }

    private function queueCounts(): array
    {
        return collect($this->queues())->map(fn (callable $queue) => $queue($this->ordersForHub())->count())->all();
    }

    public function dashboard()
    {
        $hub = $this->hub();
        $queueCounts = $this->queueCounts();

        $actionable = $this->ordersForHub()
            ->with(['originBranch', 'originHub', 'destinationHub', 'destinationBranch'])
            ->where(fn ($q) => $q
                ->where(fn ($q) => $this->queues()['incoming_linehaul']($q))
                ->orWhere(fn ($q) => $this->queues()['ready_to_dispatch']($q)))
            ->oldest('updated_at')->take(10)->get();

        $stationHubs = LogisticsBranch::where('parent_id', $hub->id)
            ->with(['municipality', 'assignedOperator'])
            ->withCount(['riderAssignments as active_riders_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')->get();

        return view('logistics.province-hub.dashboard', compact('hub', 'queueCounts', 'actionable', 'stationHubs'));
    }

    public function parcels(Request $request)
    {
        $hub    = $this->hub();
        $queues = $this->queues();
        $status = (string) $request->get('status', 'all');
        $status = $status === 'all' || array_key_exists($status, $queues) ? $status : 'all';

        $query = $this->ordersForHub()
            ->with(['buyer', 'seller', 'originBranch', 'originHub', 'destinationHub', 'destinationBranch', 'parcelScans'])
            ->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'in_transit', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed', 'delivered', 'completed']);
        if ($status !== 'all') {
            $queues[$status]($query);
        }

        $orders = $query->latest('updated_at')->paginate(15);
        $queueCounts = $this->queueCounts();

        return view('logistics.province-hub.parcels', compact('hub', 'orders', 'status', 'queueCounts'));
    }

    public function receiveLinehaul(Request $request, Order $order, LogisticsService $logistics)
    {
        $this->ensureHubOrder($order);
        $logistics->receiveAtDestinationHub($order, $request->user(), 'logistics');

        return back()->with('success', "Parcel {$order->order_number} received at {$this->hub()->name}.");
    }

    public function dispatchLinehaul(Request $request, Order $order, LogisticsService $logistics)
    {
        $this->ensureHubOrder($order);
        $logistics->dispatchLinehaul($order, $request->user(), 'logistics');

        return back()->with('success', "Parcel {$order->order_number} dispatched to destination province hub.");
    }

    public function stationHubs()
    {
        $hub = $this->hub();
        $stations = LogisticsBranch::where('parent_id', $hub->id)
            ->with(['municipality', 'assignedOperator', 'riderAssignments.rider'])
            ->orderBy('name')->get();

        return view('logistics.province-hub.stations', compact('hub', 'stations'));
    }

    public function updateStationStatus(Request $request, LogisticsBranch $branch)
    {
        abort_unless($branch->isStationHub() && (int) $branch->parent_id === (int) $this->hub()->id, 404);
        $data = $request->validate(['status' => 'required|in:active,inactive']);
        $branch->update(['status' => $data['status']]);

        return back()->with('success', $data['status'] === 'active'
            ? "{$branch->name} is active again; its portal and routing are back on."
            : "{$branch->name} is now inactive; its portal is closed and new parcels will not be routed to it.");
    }

    public function storeStationHub(Request $request, HubOperatorService $operators, BarangayImporter $barangays)
    {
        $hub = $this->hub();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'subdomain' => ['required', 'string', 'max:'.LogisticsDomains::MAX_SUBDOMAIN_LENGTH, 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('logistics_branches', 'subdomain')],
            'operator_first_name' => 'required|string|max:100',
            'operator_last_name' => 'required|string|max:100',
            'operator_sex' => 'required|in:Male,Female',
            'operator_email' => 'required|email|max:255|unique:users,email',
            'operator_password' => 'required|string|min:8|confirmed',
            'operator_contact_no' => ['required', 'regex:/^09\d{9}$/'],
            'operator_birthday' => 'required|date|before:-18 years',
        ]);

        $province = $hub->municipality()->value('province');
        $municipality = Municipality::firstOrCreate([
            'province' => $province,
            'name' => trim($data['municipality']),
        ]);

        DB::transaction(function () use ($data, $hub, $municipality, $operators): void {
            $station = LogisticsBranch::create([
                'municipality_id' => $municipality->id,
                'logistics_id' => $hub->logistics_id,
                'hub_type' => 'station_hub',
                'parent_id' => $hub->id,
                'subdomain' => $data['subdomain'],
                'name' => trim($data['name']),
                'address' => $data['address'] ?? null,
                'status' => 'active',
            ]);
            $operators->create($station, $data);
        });

        // Riders are assigned per barangay, so load the town's barangays up front.
        try {
            $added = $barangays->import($municipality);
            $note = $added > 0 ? " Loaded {$added} barangays for {$municipality->name}." : '';
        } catch (RuntimeException $exception) {
            $note = ' Barangays could not be loaded yet; the station can retry from its Riders page.';
        }

        return back()->with('success', 'Station hub and operator account created.'.$note);
    }

    private function ensureHubOrder(Order $order): void
    {
        $hub = $this->hub();
        abort_unless(
            (int) $order->logistics_id === (int) $hub->logistics_id
                && ((int) $order->origin_hub_id === (int) $hub->id
                    || (int) $order->destination_hub_id === (int) $hub->id),
            403,
            'This parcel does not pass through this province hub.',
        );
    }
}
