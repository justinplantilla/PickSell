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
                ? (request()->attributes->get('station_hub')
                    ?? request()->attributes->get('province_hub')
                    ?? LogisticsBranch::where('logistics_id', $actor->logisticsCompanyId())
                        ->where('hub_type', 'station_hub')
                        ->where('status', 'active')->orderBy('id')->first())
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

    public function dispatchLinehaul(Order $order, User $actor, string $source): void
    {
        DB::transaction(function () use ($order, $actor, $source): void {
            $locked = Order::query()->whereKey($order->id)
                ->with(['originHub', 'destinationHub'])->lockForUpdate()->firstOrFail();
            $this->ensureBranchOwnership($locked, $actor, $source);
            abort_if($locked->status !== 'sorted', 422, 'Only sorted parcels can be sent to a province hub.');
            abort_unless(
                $locked->requiresLinehaul(),
                422,
                'A province-hub transfer is only required when the parcel travels between two different province hubs.',
            );
            abort_if($locked->destination_hub_received_at, 409, 'This parcel has already arrived at its destination province hub.');

            $station = $source === 'logistics' ? request()->attributes->get('station_hub') : null;
            if ($station) {
                abort_unless((int) $locked->origin_branch_id === (int) $station->id, 403, 'Only the origin station can dispatch this parcel to linehaul.');
            }
            $provinceHub = $source === 'logistics' ? request()->attributes->get('province_hub') : null;
            if ($provinceHub) {
                abort_unless((int) $locked->origin_hub_id === (int) $provinceHub->id, 403, 'Only the origin province hub can dispatch this parcel to linehaul.');
            }

            $this->lifecycle->transition(
                $locked,
                'in_transit',
                $actor->id,
                $source,
                'Dispatched from '.$locked->originHub->name.' to '.$locked->destinationHub->name.'.',
                ['tracking_status' => 'In transit to '.$locked->destinationHub->name],
            );
            ParcelScan::create([
                'order_id' => $locked->id,
                'scanned_by' => $actor->id,
                'scan_type' => 'province_hub_linehaul_departed',
                'location' => $locked->originHub->name,
                'notes' => 'Linehaul destination: '.$locked->destinationHub->name,
                'scanned_at' => now(),
            ]);
        });
    }

    public function receiveAtDestinationHub(Order $order, User $actor, string $source): void
    {
        DB::transaction(function () use ($order, $actor, $source): void {
            $locked = Order::query()->whereKey($order->id)
                ->with('destinationHub')->lockForUpdate()->firstOrFail();
            $this->ensureBranchOwnership($locked, $actor, $source);
            abort_if(request()->attributes->get('station_hub'), 403, 'Linehaul receipt is handled by the destination province hub portal.');
            $provinceHub = request()->attributes->get('province_hub');
            if ($provinceHub) {
                abort_unless((int) $locked->destination_hub_id === (int) $provinceHub->id, 403, 'This linehaul is not addressed to this province hub.');
            }
            abort_if($locked->status !== 'in_transit', 422, 'Only linehaul parcels can be received at a province hub.');
            abort_unless($locked->destination_hub_id && $locked->destinationHub, 422, 'This parcel has no destination province hub.');

            $this->lifecycle->transition(
                $locked,
                'at_sorting_center',
                $actor->id,
                $source,
                'Received at '.$locked->destinationHub->name.'.',
                [
                    'destination_hub_received_at' => now(),
                    'tracking_status' => 'Received and scanned at sorting center',
                ],
            );
            ParcelScan::create([
                'order_id' => $locked->id,
                'scanned_by' => $actor->id,
                'scan_type' => 'province_hub_linehaul_received',
                'location' => $locked->destinationHub->name,
                'scanned_at' => now(),
            ]);
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
            $station = request()->attributes->get('station_hub');
            $provinceHub = request()->attributes->get('province_hub');
            $companyId = $station?->logistics_id ?? $provinceHub?->logistics_id ?? $actor->id;
            abort_unless((int) $order->logistics_id === (int) $companyId, 403, 'This parcel belongs to another logistics company.');

            if ($station) {
                abort_unless(
                    (int) $order->origin_branch_id === (int) $station->id
                        || (int) $order->destination_branch_id === (int) $station->id,
                    403,
                    'This parcel does not pass through this station hub.',
                );
            }
            if ($provinceHub) {
                abort_unless(
                    (int) $order->origin_hub_id === (int) $provinceHub->id
                        || (int) $order->destination_hub_id === (int) $provinceHub->id,
                    403,
                    'This parcel does not pass through this province hub.',
                );
            }
        }
    }
}
