<?php

namespace App\Services;

use App\Models\LogisticsBranch;
use App\Models\Order;
use App\Models\ParcelScan;
use App\Models\User;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\DB;

class LogisticsService
{
    public function __construct(
        private OrderLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    public function scanParcel(
        Order $order,
        User $actor,
        string $source,
        ?string $auditPermission = null,
        array $scanDetails = [],
    ): void {
        DB::transaction(function () use ($order, $actor, $source, $auditPermission, $scanDetails) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->ensureBranchOwnership($locked, $actor, $source);
            abort_if($locked->status !== 'picked_up', 422, 'Only picked-up parcels can be scanned.');
            abort_if(! in_array($locked->tracking_status, ['Pickup approved by logistics', 'Handed over to courier'], true), 422, 'Approve the pickup request before scanning the parcel.');

            $from = $locked->status;
            $reason = 'Sorting-center scan'
                .(! empty($scanDetails['scan_type']) ? ': '.$scanDetails['scan_type'] : '')
                .(! empty($scanDetails['location']) ? ' at '.$scanDetails['location'] : '')
                .(! empty($scanDetails['notes']) ? ' — '.$scanDetails['notes'] : '');
            $this->lifecycle->transition($locked, 'at_sorting_center', $actor->id, $source, $reason, [
                'tracking_status' => 'Received and scanned at sorting center',
            ]);

            $branch = $actor->role === 'logistics'
                ? LogisticsBranch::where('logistics_id', $actor->id)->where('status', 'active')->orderBy('id')->first()
                : null;
            ParcelScan::create([
                'order_id' => $locked->id,
                'scanned_by' => $actor->id,
                'scan_type' => $scanDetails['scan_type'] ?? 'sorting_center_received',
                'location' => $scanDetails['location'] ?? $branch?->name,
                'notes' => $scanDetails['notes'] ?? null,
                'scanned_at' => now(),
            ]);

            if ($auditPermission !== null) {
                $this->audit->record('logistics.parcel_scanned', $locked, [
                    'status' => ['from' => $from, 'to' => $locked->status],
                ], [], $auditPermission);
            }
        });
    }

    public function sortParcel(Order $order, User $actor, string $source): string
    {
        return DB::transaction(function () use ($order, $actor, $source) {
            $locked = Order::whereKey($order->id)->with('buyer')->lockForUpdate()->firstOrFail();
            $this->ensureBranchOwnership($locked, $actor, $source);
            abort_if($locked->status !== 'at_sorting_center', 422, 'Only parcels at the sorting center can be sorted.');
            abort_if($locked->tracking_status !== 'Received and scanned at sorting center', 422, 'Scan the parcel before sorting it.');

            $area = $locked->buyer
                ? trim(collect([$locked->buyer->municipality, $locked->buyer->province])->filter()->join(', '))
                : 'Unspecified area';

            $reason = $source === 'admin' ? 'Parcel sorted by admin' : null;
            $this->lifecycle->transition($locked, 'sorted', $actor->id, $source, $reason, [
                'tracking_status' => 'Sorted for '.$area,
            ]);

            return $area;
        });
    }

    public function approvePickup(Order $order, User $actor, string $source): void
    {
        DB::transaction(function () use ($order, $actor, $source): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->ensureBranchOwnership($locked, $actor, $source);
            abort_if($locked->status !== 'ready_for_pickup', 422, 'Only ready-for-pickup parcels can be approved.');
            abort_if($locked->tracking_status !== 'Pickup requested from seller', 422, 'This parcel has no pending pickup request.');

            $this->lifecycle->transition($locked, 'picked_up', $actor->id, $source, null, [
                'tracking_status' => 'Pickup approved by logistics',
            ]);
        });
    }

    private function ensureBranchOwnership(Order $order, User $actor, string $source): void
    {
        if ($source === 'logistics') {
            abort_unless((int) $order->logistics_id === (int) $actor->id, 403, 'This parcel belongs to another logistics branch.');
        }
    }
}
