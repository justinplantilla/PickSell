<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\BranchRider;
use App\Models\LogisticsBranch;
use App\Models\LogisticsException;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\ParcelScan;
use App\Models\RiderBarangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLogisticsOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $buyer;
    private User $seller;
    private User $courier;
    private LogisticsBranch $branch;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
        $this->courier = $this->user('courier');
        $municipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Pasig']);
        $this->barangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'San Miguel']);
        $this->branch = LogisticsBranch::create(['municipality_id' => $municipality->id, 'name' => 'Pasig Hub', 'status' => 'active']);
        $assignment = BranchRider::create(['branch_id' => $this->branch->id, 'user_id' => $this->courier->id, 'status' => 'active']);
        RiderBarangay::create(['branch_rider_id' => $assignment->id, 'barangay_id' => $this->barangay->id]);
    }

    private function user(string $role): User
    {
        static $number = 0;
        $number++;

        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => "Logistics{$number}",
            'email' => "{$role}{$number}@admin-logistics.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }

    private function order(string $status, string $trackingStatus): Order
    {
        static $number = 0;
        $number++;

        return Order::create([
            'order_number' => "ORD-ADMIN-LOGISTICS-{$number}",
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'destination_branch_id' => $this->branch->id,
            'destination_barangay_id' => $this->barangay->id,
            'product_name' => 'Logistics test parcel',
            'quantity' => 1,
            'amount' => 100,
            'commission' => 10,
            'status' => $status,
            'tracking_status' => $trackingStatus,
        ]);
    }

    public function test_logistics_index_route_keeps_the_existing_overview(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.logistics.index'))
            ->assertOk()
            ->assertSee('Logistics overview');
    }

    public function test_admin_scan_uses_permission_guard_and_records_lifecycle_and_audit(): void
    {
        $order = $this->order('picked_up', 'Pickup approved by logistics');

        $this->actingAs($this->admin)
            ->post(route('admin.logistics.scan', $order))
            ->assertRedirect();

        $this->assertSame('at_sorting_center', $order->fresh()->status);
        $this->assertSame('admin', $order->statusHistories()->where('to_status', 'at_sorting_center')->value('source'));
        $this->assertSame(Permission::LOGISTICS_SCAN, AuditLog::where('action', 'logistics.parcel_scanned')->sole()->permission);
        $this->assertSame($this->admin->id, $order->parcelScans()->sole()->scanned_by);
        $this->assertSame('sorting_center_received', $order->parcelScans()->sole()->scan_type);
    }

    public function test_logistics_pickup_approval_uses_the_shared_order_lifecycle(): void
    {
        $logisticsUser = $this->user('logistics');
        $order = $this->order('ready_for_pickup', 'Pickup requested from seller');
        $order->update(['logistics_id' => $logisticsUser->id]);

        $this->actingAs($logisticsUser)
            ->patch(route('logistics.parcels.approve-pickup', $order))
            ->assertRedirect();

        $this->assertSame('picked_up', $order->fresh()->status);
        $this->assertSame(
            ['logistics', $logisticsUser->id],
            [$order->statusHistories()->where('to_status', 'picked_up')->sole()->source,
                $order->statusHistories()->where('to_status', 'picked_up')->sole()->changed_by],
        );
    }

    public function test_admin_can_assign_only_sorted_parcels_and_records_assignment(): void
    {
        $order = $this->order('sorted', 'Sorted for Pasig, Metro Manila');
        ParcelScan::create([
            'order_id' => $order->id,
            'scanned_by' => $this->admin->id,
            'scan_type' => 'sorting_center_received',
            'location' => 'Pasig Hub',
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.logistics.assign', $order), ['courier_id' => $this->courier->id])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('assigned_to_rider', $order->status);
        $this->assertSame($this->courier->id, $order->courier_id);
        $this->assertNotNull($order->assigned_to_rider_at);
        $this->assertSame(Permission::LOGISTICS_ASSIGN_RIDER, AuditLog::where('action', 'logistics.rider_assigned')->sole()->permission);
        $courierNotification = $this->courier->notifications()->sole();
        $this->assertSame('logistics.rider_assigned', $courierNotification->data['event']);
        $this->assertSame(route('courier.orders', [], false), $courierNotification->data['url']);

        $notSorted = $this->order('at_sorting_center', 'Received and scanned at sorting center');
        $this->post(route('admin.logistics.assign', $notSorted), ['courier_id' => $this->courier->id])->assertStatus(422);
        $this->assertSame('at_sorting_center', $notSorted->fresh()->status);
    }

    public function test_admin_without_assignment_permission_cannot_assign_or_create_audit_or_notification(): void
    {
        $order = $this->order('sorted', 'Sorted for Pasig, Metro Manila');
        ParcelScan::create([
            'order_id' => $order->id,
            'scanned_by' => $this->admin->id,
            'scan_type' => 'sorting_center_received',
            'location' => 'Pasig Hub',
            'scanned_at' => now(),
        ]);
        config(['permissions.roles.admin' => [Permission::LOGISTICS_VIEW]]);

        $this->actingAs($this->admin)
            ->post(route('admin.logistics.assign', $order), ['courier_id' => $this->courier->id])
            ->assertForbidden();

        $this->assertSame('sorted', $order->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'logistics.rider_assigned')->count());
        $this->assertSame(0, $this->courier->notifications()->count());
    }

    public function test_admin_exception_resolution_uses_logistics_permission_and_existing_resolution_rules(): void
    {
        $order = $this->order('delivery_failed', 'Delivery failed');
        $order->update(['courier_id' => $this->courier->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.logistics.resolve-exception', $order), [
                'action' => 'reattempt_delivery',
                'reason' => 'Buyer confirmed another delivery attempt.',
            ])
            ->assertRedirect();

        $this->assertSame(['at_sorting_center', null], [$order->fresh()->status, $order->fresh()->courier_id]);
        $this->assertSame(Permission::LOGISTICS_RESOLVE_EXCEPTION, AuditLog::where('action', 'order.exception_resolved')->sole()->permission);
    }

    public function test_admin_can_open_and_resolve_logistics_exceptions_with_audit_permission(): void
    {
        $order = $this->order('at_sorting_center', 'Received and scanned at sorting center');

        $this->actingAs($this->admin)
            ->post(route('admin.logistics.exceptions.store', $order), [
                'type' => 'wrong_destination',
                'description' => 'Destination branch needs an operations review.',
            ])
            ->assertRedirect();

        $exception = $order->logisticsExceptions()->sole();
        $this->assertSame(Permission::LOGISTICS_MANAGE, AuditLog::where('action', 'logistics.exception_opened')->sole()->permission);

        $this->patch(route('admin.logistics.exceptions.resolve', $exception), [
            'resolution' => 'Destination checked and routing details corrected.',
        ])->assertRedirect();

        $this->assertSame('resolved', $exception->fresh()->status);
        $this->assertSame(Permission::LOGISTICS_RESOLVE_EXCEPTION, AuditLog::where('action', 'logistics.exception_resolved')->sole()->permission);
    }

    public function test_logistics_scan_route_denies_admin_without_scan_permission(): void
    {
        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::LOGISTICS_VIEW]]);
        $order = $this->order('picked_up', 'Pickup approved by logistics');

        $this->actingAs($this->admin)
            ->post(route('admin.logistics.scan', $order))
            ->assertForbidden();

        $this->assertSame('picked_up', $order->fresh()->status);
    }
}
