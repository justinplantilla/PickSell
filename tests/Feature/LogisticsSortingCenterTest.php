<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Barangay;
use App\Models\LogisticsBranch;
use App\Models\BranchRider;
use App\Models\Municipality;
use App\Models\ParcelScan;
use App\Models\RiderBarangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsSortingCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $logistics;
    private User $buyer;
    private User $courier;
    private LogisticsBranch $branch;
    private Barangay $destinationBarangay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistics = $this->user('logistics');
        $this->buyer = $this->user('buyer');
        $this->courier = $this->user('courier');
        $municipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Pasig']);
        $this->destinationBarangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'San Miguel']);
        $this->branch = LogisticsBranch::create([
            'municipality_id' => $municipality->id,
            'logistics_id' => $this->logistics->id,
            'name' => 'Pasig Hub',
            'status' => 'active',
        ]);
        $assignment = BranchRider::create(['branch_id' => $this->branch->id, 'user_id' => $this->courier->id, 'status' => 'active']);
        RiderBarangay::create(['branch_rider_id' => $assignment->id, 'barangay_id' => $this->destinationBarangay->id]);
    }

    private function user(string $role): User
    {
        static $number = 0;
        $number++;

        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => "Sorting{$number}",
            'email' => "{$role}{$number}@sorting.test",
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

    private function parcel(string $status = 'at_sorting_center'): Order
    {
        static $number = 0;
        $number++;

        $order = Order::create([
            'order_number' => "ORD-SORT-{$number}",
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->user('seller')->id,
            'logistics_id' => $this->logistics->id,
            'destination_branch_id' => $this->branch->id,
            'destination_barangay_id' => $this->destinationBarangay->id,
            'product_name' => 'Sorting test parcel',
            'quantity' => 1,
            'amount' => 100,
            'commission' => 10,
            'status' => $status,
            'tracking_status' => 'Received and scanned at sorting center',
        ]);

        if ($status === 'sorted') {
            ParcelScan::create([
                'order_id' => $order->id,
                'scanned_by' => $this->logistics->id,
                'scan_type' => 'sorting_center_received',
                'location' => 'Pasig Hub',
                'scanned_at' => now(),
            ]);
        }

        return $order;
    }

    public function test_sorting_center_exposes_sorted_stage_and_action(): void
    {
        $order = $this->parcel();

        $this->actingAs($this->logistics)
            ->get(route('logistics.parcels', ['status' => 'at_sorting_center']))
            ->assertOk()
            ->assertSee('Incoming')
            ->assertSee('Awaiting Scan')
            ->assertSee('Sorting')
            ->assertSee('Dispatch')
            ->assertSee('Exceptions')
            ->assertSee('Order ID')
            ->assertSee('Current Status')
            ->assertSee('Last Scan')
            ->assertSee('Last Updated')
            ->assertSee('Next Valid Action')
            ->assertSee('Seller Sorting')
            ->assertSee('Destination Exception')
            ->assertSee('Sorted')
            ->assertSee(route('logistics.parcels.sort', $order), false)
            ->assertDontSee(route('logistics.parcels.assign', $order), false);
    }

    public function test_missing_scan_and_destination_exception_filters_show_the_matching_queue(): void
    {
        $missingScan = $this->parcel('picked_up');
        $lastUpdated = now()->subHours(49);
        Order::whereKey($missingScan->id)->update([
            'tracking_status' => 'Pickup approved by logistics',
            'updated_at' => $lastUpdated,
        ]);
        $destinationException = $this->parcel('at_sorting_center');
        $destinationException->update(['destination_branch_id' => null]);

        $this->actingAs($this->logistics)
            ->get(route('logistics.parcels', ['status' => 'missing_scan']))
            ->assertOk()
            ->assertSee($missingScan->order_number)
            ->assertDontSee($destinationException->order_number);

        $this->get(route('logistics.parcels', ['status' => 'destination_exception']))
            ->assertOk()
            ->assertSee($destinationException->order_number)
            ->assertDontSee($missingScan->order_number);
    }

    public function test_parcel_must_be_sorted_before_rider_assignment(): void
    {
        $order = $this->parcel();

        $this->actingAs($this->logistics)
            ->patch(route('logistics.parcels.assign', $order), ['courier_id' => $this->courier->id])
            ->assertStatus(422);

        $this->assertSame('at_sorting_center', $order->fresh()->status);
    }

    public function test_rider_assignment_requires_a_scan_and_matching_active_delivery_area(): void
    {
        $order = $this->parcel('sorted');
        $order->parcelScans()->delete();

        $this->actingAs($this->logistics)
            ->patch(route('logistics.parcels.assign', $order), ['courier_id' => $this->courier->id])
            ->assertStatus(422);

        $this->assertSame('sorted', $order->fresh()->status);

        $order->parcelScans()->create([
            'scanned_by' => $this->logistics->id,
            'scan_type' => 'sorting_center_received',
            'location' => 'Pasig Hub',
            'scanned_at' => now(),
        ]);
        $unassignedCourier = $this->user('courier');

        $this->patch(route('logistics.parcels.assign', $order), ['courier_id' => $unassignedCourier->id])
            ->assertStatus(422);
        $this->assertSame('sorted', $order->fresh()->status);

        $this->patch(route('logistics.parcels.assign', $order), ['courier_id' => $this->courier->id])
            ->assertRedirect();
        $this->assertSame('assigned_to_rider', $order->fresh()->status);
    }

    public function test_logistics_staff_cannot_scan_parcels_outside_their_assigned_logistics_account(): void
    {
        $order = $this->parcel('picked_up');
        $order->update(['tracking_status' => 'Pickup approved by logistics']);
        $anotherLogistics = $this->user('logistics');

        $this->actingAs($anotherLogistics)
            ->patch(route('logistics.parcels.scan', $order))
            ->assertForbidden();

        $this->assertSame('picked_up', $order->fresh()->status);
    }

    public function test_successful_scan_records_scan_metadata_with_actor_and_timestamp(): void
    {
        $order = $this->parcel('picked_up');
        $order->update(['tracking_status' => 'Pickup approved by logistics']);

        $this->actingAs($this->logistics)
            ->patch(route('logistics.parcels.scan', $order), [
                'scan_type' => 'sorting_center_received',
                'location' => 'Pasig Sorting Hub',
                'notes' => 'Parcel label was checked.',
            ])
            ->assertRedirect();

        $scan = $order->parcelScans()->sole();
        $this->assertSame($this->logistics->id, $scan->scanned_by);
        $this->assertSame('sorting_center_received', $scan->scan_type);
        $this->assertSame('Pasig Sorting Hub', $scan->location);
        $this->assertSame('Parcel label was checked.', $scan->notes);
        $this->assertNotNull($scan->scanned_at);
    }

    public function test_logistics_exceptions_can_be_opened_and_resolved_with_audit_history(): void
    {
        $order = $this->parcel('at_sorting_center');

        $this->actingAs($this->logistics)
            ->post(route('logistics.parcels.exceptions.store', $order), [
                'type' => 'wrong_destination',
                'description' => 'Destination branch is not the buyer coverage branch.',
            ])
            ->assertRedirect();

        $exception = $order->logisticsExceptions()->sole();
        $this->assertSame('open', $exception->status);
        $this->assertSame($this->logistics->id, $exception->opened_by);

        $this->patch(route('logistics.exceptions.resolve', $exception), [
            'resolution' => 'Destination was corrected to the Pasig Hub branch.',
        ])->assertRedirect();

        $exception->refresh();
        $this->assertSame('resolved', $exception->status);
        $this->assertSame($this->logistics->id, $exception->resolved_by);
        $this->assertNotNull($exception->resolved_at);
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'logistics.exception_opened')->count());
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'logistics.exception_resolved')->count());
    }

    public function test_scanned_parcel_moves_through_sorted_before_rider_assignment(): void
    {
        $order = $this->parcel();
        ParcelScan::create([
            'order_id' => $order->id,
            'scanned_by' => $this->logistics->id,
            'scan_type' => 'sorting_center_received',
            'location' => 'Pasig Hub',
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->logistics)
            ->patch(route('logistics.parcels.sort', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('sorted', $order->status);
        $this->assertNotNull($order->sorted_at);
        $this->assertSame('Sorted for Pasig, Metro Manila', $order->tracking_status);
        $this->assertSame('logistics', $order->statusHistories()->where('to_status', 'sorted')->value('source'));

        $this->patch(route('logistics.parcels.assign', $order), ['courier_id' => $this->courier->id])
            ->assertRedirect();

        $this->assertSame('assigned_to_rider', $order->fresh()->status);
        $this->assertSame($this->courier->id, $order->fresh()->courier_id);
    }
}
