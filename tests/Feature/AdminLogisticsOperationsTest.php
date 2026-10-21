<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\LogisticsBranch;
use App\Models\LogisticsException;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithLogisticsHubs;
use Tests\TestCase;

class AdminLogisticsOperationsTest extends TestCase
{
    use InteractsWithLogisticsHubs;
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

    public function test_logistics_index_shows_company_accounts_and_branch_counts(): void
    {
        $company = $this->user('logistics');
        $company->update(['business_name' => 'PickSell Delivery', 'provider_type' => 'company']);
        $this->branch->update(['logistics_id' => $company->id]);

        $this->order('at_sorting_center', 'Received and scanned at sorting center');
        $this->actingAs($this->admin)
            ->get(route('admin.logistics.index'))
            ->assertOk()
            ->assertSee('Logistics companies')
            ->assertSee('PickSell Delivery')
            ->assertSee('1')
            ->assertDontSee('Sorting-center workload')
            ->assertDontSee('Rider Assignment');
    }

    public function test_logistics_company_overview_surfaces_return_requests_with_open_exceptions(): void
    {
        $order = $this->order('delivery_failed', 'Delivery failed');
        $return = ReturnRequest::create([
            'order_id' => $order->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'reason' => 'not_as_described',
            'details' => 'The buyer reported a concern for this parcel.',
            'status' => 'requested',
        ]);
        $exception = LogisticsException::create([
            'order_id' => $order->id,
            'opened_by' => $this->admin->id,
            'type' => 'failed_delivery',
            'status' => 'open',
            'description' => 'Delivery could not be completed.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.logistics.index'))
            ->assertOk()
            ->assertSee($exception->description)
            ->assertSee(ucfirst($return->status))
            ->assertSee('/admin/returns/'.$return->id);
    }

    public function test_logistics_pickup_approval_uses_the_shared_order_lifecycle(): void
    {
        $company = $this->user('logistics');
        $station = $this->createStationHub(
            $this->createProvinceHub($company, $this->branch->municipality, 'metro-manila'),
            $this->branch->municipality,
            'pasig',
        );
        $logisticsUser = $this->createHubOperator($station);
        $order = $this->order('ready_for_pickup', 'Pickup requested from seller');
        $order->update(['logistics_id' => $company->id, 'origin_branch_id' => $station->id]);

        $this->actingAs($logisticsUser)
            ->patch($this->stationRoute($station, 'station-hub.approve-pickup', ['order' => $order]))
            ->assertRedirect();

        $this->assertSame('picked_up', $order->fresh()->status);
        $this->assertSame(
            ['logistics', $logisticsUser->id],
            [$order->statusHistories()->where('to_status', 'picked_up')->sole()->source,
                $order->statusHistories()->where('to_status', 'picked_up')->sole()->changed_by],
        );
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

    public function test_admin_sorting_and_rider_assignment_endpoints_are_removed(): void
    {
        $order = $this->order('sorted', 'Sorted for Pasig, Metro Manila');

        $this->actingAs($this->admin)
            ->post("/admin/logistics/{$order->id}/scan")
            ->assertNotFound();
        $this->post("/admin/logistics/{$order->id}/assign", ['courier_id' => $this->courier->id])
            ->assertNotFound();
        $this->assertSame('sorted', $order->fresh()->status);
    }
}
