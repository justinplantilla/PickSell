<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\BranchRider;
use App\Models\DeliveryLog;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\ParcelScan;
use App\Models\RiderBarangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithLogisticsHubs;
use Tests\TestCase;

class LogisticsSortingCenterTest extends TestCase
{
    use InteractsWithLogisticsHubs;
    use RefreshDatabase;

    private User $logistics;

    private User $operator;

    private User $buyer;

    private User $courier;

    private Municipality $municipality;

    private LogisticsBranch $provinceHub;

    private LogisticsBranch $branch;

    private Barangay $destinationBarangay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistics = $this->user('logistics');
        $this->buyer = $this->user('buyer');
        $this->courier = $this->user('courier');
        $this->municipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Pasig']);
        $this->destinationBarangay = Barangay::create(['municipality_id' => $this->municipality->id, 'name' => 'San Miguel']);
        $this->provinceHub = $this->createProvinceHub($this->logistics, $this->municipality, 'metro-manila');
        $this->branch = $this->createStationHub($this->provinceHub, $this->municipality, 'pasig', 'Pasig Hub');
        $this->operator = $this->createHubOperator($this->branch);
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
            'origin_branch_id' => $this->branch->id,
            'destination_branch_id' => $this->branch->id,
            'origin_hub_id' => $this->provinceHub->id,
            'destination_hub_id' => $this->provinceHub->id,
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
                'scanned_by' => $this->operator->id,
                'scan_type' => 'sorting_center_received',
                'location' => 'Pasig Hub',
                'scanned_at' => now(),
            ]);
        }

        return $order;
    }

    /** A station hub belonging to a different logistics company, with its own operator. */
    private function otherCompanyOperator(): User
    {
        $company = $this->user('logistics');
        $hub = $this->createProvinceHub($company, $this->municipality, 'rival-hub');
        $station = $this->createStationHub($hub, $this->municipality, 'rival-pasig');

        return $this->createHubOperator($station);
    }

    private function url(string $name, array $parameters = []): string
    {
        return $this->stationRoute($this->branch, $name, $parameters);
    }

    public function test_sorting_center_exposes_sorted_stage_and_action(): void
    {
        $order = $this->parcel();

        $this->actingAs($this->operator)
            ->get($this->url('station-hub.parcels', ['status' => 'at_sorting_center']))
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
            ->assertSee('Destination Exception')
            ->assertSee('Sorted')
            ->assertSee($this->url('station-hub.sort', ['order' => $order]), false)
            ->assertDontSee($this->url('station-hub.assign', ['order' => $order]), false);
    }

    public function test_intake_and_sort_queues_only_show_parcels_ready_for_that_task(): void
    {
        $awaitingScan = $this->parcel('picked_up');
        $awaitingScan->update(['tracking_status' => 'Pickup approved by logistics']);
        $pickupVerification = $this->parcel('picked_up');
        $pickupVerification->update(['tracking_status' => null]);
        $awaitingSort = $this->parcel('at_sorting_center');
        $needsScanVerification = $this->parcel('at_sorting_center');
        $needsScanVerification->update(['tracking_status' => null]);

        $this->actingAs($this->operator)
            ->get($this->url('station-hub.parcels', ['status' => 'awaiting_scan']))
            ->assertOk()
            ->assertSee($awaitingScan->order_number)
            ->assertSee('Scan type')
            ->assertSee('Save Scan')
            ->assertDontSee($pickupVerification->order_number);

        $this->get($this->url('station-hub.parcels', ['status' => 'verification_required']))
            ->assertOk()
            ->assertSee($pickupVerification->order_number)
            ->assertSee($needsScanVerification->order_number)
            ->assertDontSee($awaitingScan->order_number);

        $this->get($this->url('station-hub.parcels', ['status' => 'awaiting_sort']))
            ->assertOk()
            ->assertSee($awaitingSort->order_number)
            ->assertDontSee($needsScanVerification->order_number);
    }

    public function test_rider_unavailable_queue_uses_the_parcels_destination_coverage(): void
    {
        $covered = $this->parcel('sorted');
        $uncovered = $this->parcel('sorted');
        $otherBarangay = Barangay::create([
            'municipality_id' => $this->destinationBarangay->municipality_id,
            'name' => 'Manggahan',
        ]);
        $uncovered->update(['destination_barangay_id' => $otherBarangay->id]);

        $this->actingAs($this->operator)
            ->get($this->url('station-hub.parcels', ['status' => 'rider_unavailable']))
            ->assertOk()
            ->assertSee($uncovered->order_number)
            ->assertDontSee($covered->order_number)
            ->assertSee('No approved rider covers this destination.');
    }

    public function test_exception_details_and_resolution_are_available_from_the_parcel_row(): void
    {
        $order = $this->parcel();
        $exception = $order->logisticsExceptions()->create([
            'opened_by' => $this->operator->id,
            'type' => 'wrong_destination',
            'status' => 'open',
            'description' => 'Destination branch needs to be verified.',
        ]);

        $this->actingAs($this->operator)
            ->get($this->url('station-hub.parcels', ['status' => 'at_sorting_center']))
            ->assertOk()
            ->assertSee('Open wrong destination exception')
            ->assertSee('Destination branch needs to be verified.')
            ->assertSee($this->url('station-hub.exceptions.resolve', ['exception' => $exception]), false)
            ->assertSee('Recent exception history');
    }

    public function test_missing_scan_and_destination_exception_filters_show_the_matching_queue(): void
    {
        $missingScan = $this->parcel('picked_up');
        Order::whereKey($missingScan->id)->update([
            'tracking_status' => 'Pickup approved by logistics',
            'updated_at' => now()->subHours(49),
        ]);
        $destinationException = $this->parcel('at_sorting_center');
        $destinationException->update(['destination_barangay_id' => null]);
        $otherMunicipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Manila']);
        $otherBarangay = Barangay::create(['municipality_id' => $otherMunicipality->id, 'name' => 'Ermita']);
        $misrouted = $this->parcel('at_sorting_center');
        $misrouted->update(['destination_barangay_id' => $otherBarangay->id]);

        $this->actingAs($this->operator)
            ->get($this->url('station-hub.parcels', ['status' => 'missing_scan']))
            ->assertOk()
            ->assertSee($missingScan->order_number)
            ->assertDontSee($destinationException->order_number);

        $this->get($this->url('station-hub.parcels', ['status' => 'destination_exception']))
            ->assertOk()
            ->assertSee($destinationException->order_number)
            ->assertSee($misrouted->order_number)
            ->assertDontSee($missingScan->order_number);
    }

    public function test_parcel_must_be_sorted_before_rider_assignment(): void
    {
        $order = $this->parcel();

        $this->actingAs($this->operator)
            ->patch($this->url('station-hub.assign', ['order' => $order]), ['courier_id' => $this->courier->id])
            ->assertStatus(422);

        $this->assertSame('at_sorting_center', $order->fresh()->status);
    }

    public function test_rider_assignment_requires_a_scan_and_matching_active_delivery_area(): void
    {
        $order = $this->parcel('sorted');
        $order->parcelScans()->delete();

        $this->actingAs($this->operator)
            ->patch($this->url('station-hub.assign', ['order' => $order]), ['courier_id' => $this->courier->id])
            ->assertStatus(422);

        $this->assertSame('sorted', $order->fresh()->status);

        $order->parcelScans()->create([
            'scanned_by' => $this->operator->id,
            'scan_type' => 'sorting_center_received',
            'location' => 'Pasig Hub',
            'scanned_at' => now(),
        ]);
        $unassignedCourier = $this->user('courier');

        $this->patch($this->url('station-hub.assign', ['order' => $order]), ['courier_id' => $unassignedCourier->id])
            ->assertStatus(422);
        $this->assertSame('sorted', $order->fresh()->status);

        $this->patch($this->url('station-hub.assign', ['order' => $order]), ['courier_id' => $this->courier->id])
            ->assertRedirect();
        $this->assertSame('assigned_to_rider', $order->fresh()->status);
    }

    public function test_operators_cannot_scan_parcels_of_another_logistics_company(): void
    {
        $order = $this->parcel('picked_up');
        $order->update(['tracking_status' => 'Pickup approved by logistics']);
        $rival = $this->otherCompanyOperator();

        $this->actingAs($rival)
            ->patch($this->stationRoute($rival->assignedHub, 'station-hub.scan', ['order' => $order]))
            ->assertForbidden();

        $this->assertSame('picked_up', $order->fresh()->status);
    }

    public function test_operators_cannot_use_another_stations_portal(): void
    {
        $order = $this->parcel('picked_up');
        $rival = $this->otherCompanyOperator();

        $this->actingAs($rival)
            ->patch($this->url('station-hub.scan', ['order' => $order]))
            ->assertForbidden();

        $this->assertSame('picked_up', $order->fresh()->status);
    }

    public function test_operators_cannot_sort_or_assign_parcels_owned_by_another_company(): void
    {
        $sortingOrder = $this->parcel('at_sorting_center');
        $order = $this->parcel('sorted');
        $rival = $this->otherCompanyOperator();
        $rivalStation = $rival->assignedHub;

        $this->actingAs($rival)
            ->patch($this->stationRoute($rivalStation, 'station-hub.sort', ['order' => $sortingOrder]))
            ->assertForbidden();

        $this->patch($this->stationRoute($rivalStation, 'station-hub.assign', ['order' => $order]), ['courier_id' => $this->courier->id])
            ->assertForbidden();

        $this->assertSame('at_sorting_center', $sortingOrder->fresh()->status);
        $this->assertSame('sorted', $order->fresh()->status);
        $this->assertDatabaseMissing('delivery_assignments', [
            'delivery_id' => $order->delivery->id,
            'rider_id' => $this->courier->id,
        ]);
    }

    public function test_operators_cannot_open_or_resolve_another_companys_exception(): void
    {
        $order = $this->parcel('at_sorting_center');
        $exception = $order->logisticsExceptions()->create([
            'opened_by' => $this->operator->id,
            'type' => 'wrong_destination',
            'status' => 'open',
            'description' => 'The parcel was routed to a different branch.',
        ]);
        $rival = $this->otherCompanyOperator();
        $rivalStation = $rival->assignedHub;

        $this->actingAs($rival)
            ->post($this->stationRoute($rivalStation, 'station-hub.exceptions.store', ['order' => $order]), [
                'type' => 'wrong_destination',
                'description' => 'Attempt to open another branch exception.',
            ])
            ->assertForbidden();

        $this->patch($this->stationRoute($rivalStation, 'station-hub.exceptions.resolve', ['exception' => $exception]), [
            'resolution' => 'Attempt to resolve another branch exception.',
        ])->assertForbidden();

        $this->assertSame('open', $exception->fresh()->status);
    }

    public function test_station_dashboard_shows_only_its_own_parcels(): void
    {
        $ownOrder = $this->parcel('at_sorting_center');
        $otherOrder = $this->parcel('picked_up');
        $otherOrder->update(['logistics_id' => $this->user('logistics')->id]);

        $this->actingAs($this->operator)
            ->get($this->url('station-hub.dashboard'))
            ->assertOk()
            ->assertSee($ownOrder->order_number)
            ->assertDontSee($otherOrder->order_number)
            ->assertSee('Failed Deliveries');
    }

    public function test_company_dashboard_lists_only_its_own_province_hubs(): void
    {
        $rivalHub = $this->otherCompanyOperator()->assignedHub->parent;

        $this->actingAs($this->logistics)
            ->get($this->companyRoute('logistics.dashboard'))
            ->assertOk()
            ->assertSee($this->provinceHub->name)
            ->assertDontSee($rivalHub->name)
            ->assertSee('/logistics/branches', false)
            ->assertSee('/logistics/complaints', false);
    }

    public function test_successful_scan_records_scan_metadata_with_actor_and_timestamp(): void
    {
        $order = $this->parcel('picked_up');
        $order->update(['tracking_status' => 'Pickup approved by logistics']);

        $this->actingAs($this->operator)
            ->patch($this->url('station-hub.scan', ['order' => $order]), [
                'scan_type' => 'sorting_center_received',
                'location' => 'Pasig Sorting Hub',
                'notes' => 'Parcel label was checked.',
            ])
            ->assertRedirect();

        $scan = $order->parcelScans()->sole();
        $this->assertSame($this->operator->id, $scan->scanned_by);
        $this->assertSame('sorting_center_received', $scan->scan_type);
        $this->assertSame('Pasig Sorting Hub', $scan->location);
        $this->assertSame('Parcel label was checked.', $scan->notes);
        $this->assertNotNull($scan->scanned_at);
        $scanLog = DeliveryLog::where('delivery_id', $order->delivery->id)
            ->where('to_status', 'at_sorting_center')
            ->sole();
        $this->assertStringContainsString('Pasig Sorting Hub', $scanLog->note);
        $this->assertStringContainsString('Parcel label was checked.', $scanLog->note);
    }

    public function test_delivery_report_filters_use_canonical_order_statuses(): void
    {
        $order = $this->parcel('out_for_delivery');

        $this->actingAs($this->logistics)
            ->get($this->companyRoute('logistics.reports.deliveries', ['status' => 'out_for_delivery']))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('value="out_for_delivery" selected', false);

        $this->get($this->companyRoute('logistics.reports.deliveries', ['status' => 'not_a_status']))
            ->assertSessionHasErrors('status');
    }

    public function test_logistics_exceptions_can_be_opened_and_resolved_with_audit_history(): void
    {
        $order = $this->parcel('at_sorting_center');

        $this->actingAs($this->operator)
            ->post($this->url('station-hub.exceptions.store', ['order' => $order]), [
                'type' => 'wrong_destination',
                'description' => 'Destination branch is not the buyer coverage branch.',
            ])
            ->assertRedirect();

        $exception = $order->logisticsExceptions()->sole();
        $this->assertSame('open', $exception->status);
        $this->assertSame($this->operator->id, $exception->opened_by);

        $this->patch($this->url('station-hub.exceptions.resolve', ['exception' => $exception]), [
            'resolution' => 'Destination was corrected to the Pasig Hub branch.',
        ])->assertRedirect();

        $exception->refresh();
        $this->assertSame('resolved', $exception->status);
        $this->assertSame($this->operator->id, $exception->resolved_by);
        $this->assertNotNull($exception->resolved_at);
        $this->assertSame(1, AuditLog::where('action', 'logistics.exception_opened')->count());
        $this->assertSame(1, AuditLog::where('action', 'logistics.exception_resolved')->count());
    }

    public function test_scanned_parcel_moves_through_sorted_before_rider_assignment(): void
    {
        $order = $this->parcel();
        ParcelScan::create([
            'order_id' => $order->id,
            'scanned_by' => $this->operator->id,
            'scan_type' => 'sorting_center_received',
            'location' => 'Pasig Hub',
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->operator)
            ->patch($this->url('station-hub.sort', ['order' => $order]))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('sorted', $order->status);
        $this->assertNotNull($order->sorted_at);
        $this->assertSame('Sorted for Pasig, Metro Manila', $order->tracking_status);
        $this->assertSame('logistics', $order->statusHistories()->where('to_status', 'sorted')->value('source'));

        $this->patch($this->url('station-hub.assign', ['order' => $order]), ['courier_id' => $this->courier->id])
            ->assertRedirect();

        $this->assertSame('assigned_to_rider', $order->fresh()->status);
        $this->assertSame($this->courier->id, $order->fresh()->courier_id);
        $this->assertDatabaseHas('delivery_assignments', [
            'delivery_id' => $order->delivery->id,
            'rider_id' => $this->courier->id,
            'status' => 'offered',
        ]);
    }
}
