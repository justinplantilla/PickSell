<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BranchRider;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\ParcelScan;
use App\Models\RiderBarangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithLogisticsHubs;
use Tests\TestCase;

class StationHubPortalTest extends TestCase
{
    use InteractsWithLogisticsHubs;
    use RefreshDatabase;

    private User $logistics;

    /** @var array<string, LogisticsBranch> */
    private array $provinceHubs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->logistics = $this->user('logistics');
    }

    public function test_company_creates_province_hubs_and_province_hubs_create_station_hubs(): void
    {
        \Illuminate\Support\Facades\Http::fake(['psgc.gitlab.io/*' => \Illuminate\Support\Facades\Http::response([], 503)]);
        $this->actingAs($this->logistics)
            ->post($this->companyRoute('logistics.branches.store'), [
                'name' => 'Laguna Province Hub',
                'hub_type' => 'province_hub',
                'province' => 'Laguna',
                'municipality' => 'Santa Cruz',
                'subdomain' => 'laguna',
                ...$this->operatorFields('laguna.operator@hubs.test'),
            ])
            ->assertRedirect();

        $provinceHub = LogisticsBranch::where('hub_type', 'province_hub')->sole();
        $provinceOperator = User::where('email', 'laguna.operator@hubs.test')->sole();
        $this->assertSame($this->logistics->id, $provinceHub->logistics_id);
        $this->assertSame($provinceHub->id, $provinceOperator->assigned_hub_id);
        $this->assertSame('https://hub-laguna.pick-sell.shop/logistics/dashboard', $provinceHub->portalUrl());

        $this->actingAs($provinceOperator)
            ->post($this->provinceRoute($provinceHub, 'province-hub.stations.store'), [
                'name' => 'Calamba Station',
                'subdomain' => 'calamba',
                'municipality' => 'Calamba',
                ...$this->operatorFields('calamba.operator@hubs.test'),
            ])
            ->assertRedirect();

        $station = LogisticsBranch::where('subdomain', 'calamba')->sole();
        $this->assertSame('station_hub', $station->hub_type);
        $this->assertSame($provinceHub->id, $station->parent_id);
        $this->assertSame($this->logistics->id, $station->logistics_id);
        $this->assertSame('Laguna', $station->municipality->province);
        $this->assertSame($station->id, User::where('email', 'calamba.operator@hubs.test')->value('assigned_hub_id'));
    }

    public function test_station_portal_shows_only_its_parcels(): void
    {
        $station = $this->station('santa-cruz');
        $otherStation = $this->station('calamba');
        $localOrder = $this->order($station, 'LOCAL-STATION-ORDER');
        $otherOrder = $this->order($otherStation, 'OTHER-STATION-ORDER');

        $this->actingAs($this->createHubOperator($station))
            ->get($this->stationRoute($station, 'station-hub.parcels'))
            ->assertOk()
            ->assertSee($localOrder->order_number)
            ->assertDontSee($otherOrder->order_number)
            ->assertSee('Station Operations');
    }

    public function test_station_cannot_operate_on_a_parcel_for_another_station(): void
    {
        $station = $this->station('santa-cruz');
        $otherStation = $this->station('calamba');
        $order = $this->order($otherStation, 'OTHER-STATION-PICKUP', 'picked_up');
        $order->update(['tracking_status' => 'Pickup approved by logistics']);

        $this->actingAs($this->createHubOperator($station))
            ->patch($this->stationRoute($station, 'station-hub.scan', ['order' => $order]))
            ->assertForbidden();

        $this->assertSame('picked_up', $order->fresh()->status);
    }

    public function test_hub_portals_only_admit_their_assigned_operator(): void
    {
        $station = $this->station('santa-cruz');
        $otherOperator = $this->createHubOperator($this->station('calamba'));

        $this->actingAs($otherOperator)
            ->get($this->stationRoute($station, 'station-hub.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->logistics)
            ->get($this->stationRoute($station, 'station-hub.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->logistics)
            ->get($this->provinceRoute($station->parent, 'province-hub.dashboard'))
            ->assertForbidden();
    }

    public function test_unknown_hub_subdomain_is_not_found(): void
    {
        $this->actingAs($this->logistics)
            ->get('https://station-nowhere.pick-sell.shop/logistics/dashboard')
            ->assertNotFound();

        // Two-level hosts are not hub portals.
        $this->assertNull(\App\Support\LogisticsDomains::parseHubHost('santa-cruz.station.pick-sell.shop'));
        $this->assertSame(['station_hub', 'santa-cruz'], \App\Support\LogisticsDomains::parseHubHost('station-santa-cruz.pick-sell.shop'));
        $this->assertSame(['province_hub', 'liliw-demo-hub'], \App\Support\LogisticsDomains::parseHubHost('hub-liliw-demo-hub.pick-sell.shop'));
    }

    public function test_hub_operator_is_sent_to_their_own_portal(): void
    {
        $station = $this->station('santa-cruz');
        $operator = $this->createHubOperator($station);

        $this->actingAs($operator)
            ->get($this->companyRoute('logistics.dashboard'))
            ->assertRedirect($station->portalUrl());

        $this->actingAs($operator)
            ->get($this->stationRoute($station, 'station-hub.parcels', ['status' => 'all']))
            ->assertOk();

        // Company-only pages do not exist on hub subdomains.
        $this->get('https://'.$station->portalHost().'/logistics/branches')
            ->assertNotFound();
    }

    public function test_hub_operator_login_redirects_to_their_portal(): void
    {
        $station = $this->station('santa-cruz');
        $operator = $this->createHubOperator($station);

        $this->post('https://'.config('logistics.superadmin_domain').'/login', [
            'email' => $operator->email,
            'password' => 'password',
        ])->assertRedirect($station->portalUrl());
    }

    public function test_station_can_manage_its_riders(): void
    {
        $station = $this->station('santa-cruz');
        $operator = $this->createHubOperator($station);
        $courier = $this->user('courier');
        $poblacion = Barangay::create(['municipality_id' => $station->municipality_id, 'name' => 'Poblacion']);
        $bagumbayan = Barangay::create(['municipality_id' => $station->municipality_id, 'name' => 'Bagumbayan']);

        $this->actingAs($operator)
            ->get($this->stationRoute($station, 'station-hub.riders'))
            ->assertOk()
            ->assertSee($courier->full_name);

        foreach ([$poblacion, $bagumbayan] as $barangay) {
            $this->post($this->stationRoute($station, 'station-hub.riders.assign'), [
                'rider_id' => $courier->id,
                'barangay_id' => $barangay->id,
            ])->assertRedirect();
        }

        $assignment = BranchRider::where('branch_id', $station->id)->where('user_id', $courier->id)->sole();
        $this->assertSame(2, $assignment->barangays()->count());
        $this->assertSame(1, $assignment->barangays()->where('is_primary', true)->count());
    }

    public function test_intra_province_parcels_skip_linehaul(): void
    {
        $origin = $this->station('santa-cruz');
        $destination = $this->station('calamba');
        $order = $this->order($destination, 'INTRAPROVINCE-PARCEL', 'sorted');
        $order->update(['origin_branch_id' => $origin->id, 'origin_hub_id' => $origin->parent_id]);
        $courier = $this->riderFor($destination, $order);
        $this->scanned($order, $origin);

        $this->assertFalse($order->fresh()->requiresLinehaul());

        $this->actingAs($this->createHubOperator($destination))
            ->patch($this->stationRoute($destination, 'station-hub.assign', ['order' => $order]), ['courier_id' => $courier->id])
            ->assertRedirect();

        $this->assertSame('assigned_to_rider', $order->fresh()->status);
    }

    public function test_inter_province_parcels_need_linehaul_before_rider_assignment(): void
    {
        $origin = $this->station('santa-cruz');
        $destination = $this->station('tagaytay', 'Cavite');
        $order = $this->order($destination, 'INTERPROVINCE-PARCEL', 'sorted');
        $order->update(['origin_branch_id' => $origin->id, 'origin_hub_id' => $origin->parent_id]);
        $courier = $this->riderFor($destination, $order);
        $this->scanned($order, $origin);
        $destinationOperator = $this->createHubOperator($destination);

        $this->actingAs($destinationOperator)
            ->patch($this->stationRoute($destination, 'station-hub.assign', ['order' => $order]), ['courier_id' => $courier->id])
            ->assertUnprocessable();

        $originHub = $origin->parent;
        $this->actingAs($this->createHubOperator($originHub))
            ->patch($this->provinceRoute($originHub, 'province-hub.dispatch-linehaul', ['order' => $order]))
            ->assertRedirect();

        $this->assertSame('in_transit', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->linehaul_dispatched_at);

        $this->patch($this->provinceRoute($originHub, 'province-hub.receive-linehaul', ['order' => $order]))
            ->assertForbidden();

        $destinationHub = $destination->parent;
        $this->actingAs($this->createHubOperator($destinationHub))
            ->patch($this->provinceRoute($destinationHub, 'province-hub.receive-linehaul', ['order' => $order]))
            ->assertRedirect();

        $this->assertSame('at_sorting_center', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->destination_hub_received_at);
        $this->assertSame('province_hub_linehaul_received', $order->parcelScans()->latest('id')->value('scan_type'));

        $this->actingAs($destinationOperator)
            ->patch($this->stationRoute($destination, 'station-hub.sort', ['order' => $order]))
            ->assertRedirect();
        $this->assertSame('sorted', $order->fresh()->status);

        $this->patch($this->stationRoute($destination, 'station-hub.assign', ['order' => $order]), ['courier_id' => $courier->id])
            ->assertRedirect();
        $this->assertSame($courier->id, $order->fresh()->courier_id);
    }

    public function test_every_portal_page_renders(): void
    {
        $origin = $this->station('santa-cruz');
        $destination = $this->station('tagaytay', 'Cavite');
        $order = $this->order($destination, 'RENDER-PARCEL', 'sorted');
        $order->update(['origin_branch_id' => $origin->id, 'origin_hub_id' => $origin->parent_id]);
        $provinceHub = $origin->parent;

        $this->actingAs($this->logistics);
        foreach (['logistics.dashboard', 'logistics.branches', 'logistics.account', 'logistics.reports.deliveries'] as $name) {
            $this->get($this->companyRoute($name))->assertOk();
        }
        $this->get($this->companyRoute('logistics.branches.edit', ['branch' => $provinceHub]))
            ->assertOk()
            ->assertSee('This hub has no operator yet');

        $this->actingAs($this->createHubOperator($provinceHub));
        $this->get($this->provinceRoute($provinceHub, 'province-hub.dashboard'))->assertOk()->assertSee('RENDER-PARCEL')->assertSee('Dispatch');
        foreach (['all', 'incoming_linehaul', 'ready_to_dispatch', 'outbound_linehaul', 'received_here', 'delivery_failed'] as $queue) {
            $this->get($this->provinceRoute($provinceHub, 'province-hub.parcels', ['status' => $queue]))->assertOk();
        }
        $this->get($this->provinceRoute($provinceHub, 'province-hub.parcels', ['status' => 'ready_to_dispatch']))->assertSee('RENDER-PARCEL');
        $this->get($this->provinceRoute($provinceHub, 'province-hub.stations'))->assertOk()->assertSee($origin->portalHost());
        $this->get($this->provinceRoute($provinceHub, 'province-hub.account'))->assertOk()->assertSee('Hub Assignment')->assertDontSee('Delete Account');

        $this->actingAs($this->createHubOperator($origin));
        $this->get($this->stationRoute($origin, 'station-hub.dashboard'))->assertOk()->assertSee('No riders are assigned');
        $this->get($this->stationRoute($origin, 'station-hub.parcels'))->assertOk()->assertSee('RENDER-PARCEL');
        $this->get($this->stationRoute($origin, 'station-hub.riders'))->assertOk();
        $this->get($this->stationRoute($origin, 'station-hub.account'))->assertOk()->assertSee($origin->name);
    }

    public function test_province_hub_can_deactivate_only_its_own_stations(): void
    {
        $station = $this->station('santa-cruz');
        $otherProvinceStation = $this->station('tagaytay', 'Cavite');
        $this->actingAs($this->createHubOperator($station->parent));

        $this->patch($this->provinceRoute($station->parent, 'province-hub.stations.status', ['branch' => $station]), ['status' => 'inactive'])
            ->assertRedirect();
        $this->assertSame('inactive', $station->fresh()->status);

        $this->patch($this->provinceRoute($station->parent, 'province-hub.stations.status', ['branch' => $otherProvinceStation]), ['status' => 'inactive'])
            ->assertNotFound();
        $this->assertSame('active', $otherProvinceStation->fresh()->status);
    }

    public function test_hub_operator_can_change_their_temporary_password(): void
    {
        $station = $this->station('santa-cruz');
        $operator = $this->createHubOperator($station);

        $this->actingAs($operator)
            ->patch($this->stationRoute($station, 'station-hub.account.password'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $operator->fresh()->password));
    }

    public function test_forbidden_hub_page_offers_to_switch_account(): void
    {
        $station = $this->station('santa-cruz');

        $this->actingAs($this->logistics)
            ->get($this->stationRoute($station, 'station-hub.dashboard'))
            ->assertForbidden()
            ->assertSee('This station hub is not assigned to your account.')
            ->assertSee('Sign out and switch account')
            ->assertSee($this->logistics->email);
    }

    public function test_station_reviews_only_courier_applications_from_its_town(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $station = $this->station('santa-cruz');
        $operator = $this->createHubOperator($station);
        $barangay = Barangay::create(['municipality_id' => $station->municipality_id, 'name' => 'Poblacion']);
        $local = $this->applicant('Sta. Cruz');
        $elsewhere = $this->applicant('Calamba');

        $this->actingAs($operator)
            ->get($this->stationRoute($station, 'station-hub.applications'))
            ->assertOk()
            ->assertSee($local->email)
            ->assertDontSee($elsewhere->email);

        $this->patch($this->stationRoute($station, 'station-hub.applications.approve', ['user' => $elsewhere]), [
            'checklist' => ['identity' => 1, 'contact' => 1, 'address' => 1],
        ])->assertNotFound();

        $this->patch($this->stationRoute($station, 'station-hub.applications.approve', ['user' => $local]), [
            'checklist' => ['identity' => 1],
        ])->assertSessionHasErrors();
        $this->assertSame('pending', $local->fresh()->status);

        $this->patch($this->stationRoute($station, 'station-hub.applications.approve', ['user' => $local]), [
            'checklist' => ['identity' => 1, 'contact' => 1, 'address' => 1],
            'barangay_id' => $barangay->id,
        ])->assertRedirect();

        $this->assertSame('approved', $local->fresh()->status);
        $assignment = BranchRider::where('branch_id', $station->id)->where('user_id', $local->id)->sole();
        $this->assertSame('active', $assignment->status);
        $this->assertSame([$barangay->id], $assignment->barangays()->pluck('barangay_id')->all());
        $this->assertDatabaseHas('registration_reviews', ['user_id' => $local->id, 'reviewed_by' => $operator->id, 'decision' => 'approved']);

        $this->patch($this->stationRoute($station, 'station-hub.applications.disapprove', ['user' => $elsewhere]), ['reason' => 'Wrong town.'])
            ->assertNotFound();
    }

    public function test_station_can_disapprove_an_application_with_a_reason(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $station = $this->station('santa-cruz');
        $applicant = $this->applicant('Santa Cruz');

        $this->actingAs($this->createHubOperator($station))
            ->patch($this->stationRoute($station, 'station-hub.applications.disapprove', ['user' => $applicant]), ['reason' => 'The OR/CR photo is unreadable.'])
            ->assertRedirect();

        $this->assertSame('disapproved', $applicant->fresh()->status);
        $this->assertDatabaseHas('registration_reviews', ['user_id' => $applicant->id, 'decision' => 'disapproved', 'reason' => 'The OR/CR photo is unreadable.']);
    }

    public function test_station_loads_its_barangays_from_psgc(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'psgc.gitlab.io/api/provinces/' => \Illuminate\Support\Facades\Http::response([['code' => '043400000', 'name' => 'Laguna']]),
            'psgc.gitlab.io/api/provinces/043400000/cities-municipalities/' => \Illuminate\Support\Facades\Http::response([['code' => '043426000', 'name' => 'Santa Cruz']]),
            'psgc.gitlab.io/api/cities-municipalities/043426000/barangays/' => \Illuminate\Support\Facades\Http::response([
                ['code' => '1', 'name' => 'Poblacion I'], ['code' => '2', 'name' => 'Bagumbayan'], ['code' => '3', 'name' => 'Labuin'],
            ]),
        ]);
        $station = $this->station('santa-cruz');
        Barangay::create(['municipality_id' => $station->municipality_id, 'name' => 'Bagumbayan']);

        $this->actingAs($this->createHubOperator($station))
            ->post($this->stationRoute($station, 'station-hub.barangays.import'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Added 2 barangays for Santa-cruz.');

        $this->assertSame(3, Barangay::where('municipality_id', $station->municipality_id)->count());
    }

    public function test_station_can_take_over_a_rider_from_an_old_pre_hub_branch(): void
    {
        $station = $this->station('santa-cruz');
        $legacyBranch = LogisticsBranch::create(['municipality_id' => $station->municipality_id, 'name' => 'Old Santa Cruz Branch', 'status' => 'active']);
        $rider = $this->user('courier');
        BranchRider::create(['branch_id' => $legacyBranch->id, 'user_id' => $rider->id, 'status' => 'active']);
        $barangay = Barangay::create(['municipality_id' => $station->municipality_id, 'name' => 'Poblacion']);
        $operator = $this->createHubOperator($station);

        $this->actingAs($operator)
            ->get($this->stationRoute($station, 'station-hub.riders'))
            ->assertOk()
            ->assertSee('moves from Old Santa Cruz Branch');

        $this->post($this->stationRoute($station, 'station-hub.riders.assign'), ['rider_id' => $rider->id, 'barangay_id' => $barangay->id])
            ->assertRedirect();

        $this->assertSame('inactive', BranchRider::where('branch_id', $legacyBranch->id)->where('user_id', $rider->id)->value('status'));
        $this->assertSame('active', BranchRider::where('branch_id', $station->id)->where('user_id', $rider->id)->value('status'));

        // A rider working at another real station cannot be taken.
        $otherStation = $this->station('calamba');
        $busy = $this->user('courier');
        BranchRider::create(['branch_id' => $otherStation->id, 'user_id' => $busy->id, 'status' => 'active']);
        $this->post($this->stationRoute($station, 'station-hub.riders.assign'), ['rider_id' => $busy->id, 'barangay_id' => $barangay->id])
            ->assertStatus(409);
    }

    private function applicant(string $municipality): User
    {
        $applicant = $this->user('courier');
        $applicant->update([
            'status' => 'pending',
            'municipality' => $municipality,
            'province' => 'Laguna',
            'id_upload' => 'uploads/ids/test.jpg',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'ABC 1234',
            'delivery_area' => 'Poblacion',
        ]);

        return $applicant;
    }

    private function station(string $subdomain, string $province = 'Laguna'): LogisticsBranch
    {
        $hub = $this->provinceHubs[$province] ??= $this->createProvinceHub(
            $this->logistics,
            Municipality::firstOrCreate(['province' => $province, 'name' => $province === 'Laguna' ? 'Santa Cruz' : $province.' City']),
            strtolower($province),
        );
        $municipality = Municipality::firstOrCreate(['province' => $province, 'name' => ucfirst($subdomain)]);

        return $this->createStationHub($hub, $municipality, $subdomain);
    }

    private function order(LogisticsBranch $destination, string $number, string $status = 'at_sorting_center'): Order
    {
        $barangay = Barangay::firstOrCreate([
            'municipality_id' => $destination->municipality_id,
            'name' => 'Poblacion',
        ]);

        return Order::create([
            'order_number' => $number,
            'buyer_id' => $this->user('buyer')->id,
            'seller_id' => $this->user('seller')->id,
            'logistics_id' => $this->logistics->id,
            'destination_branch_id' => $destination->id,
            'destination_hub_id' => $destination->parent_id,
            'destination_barangay_id' => $barangay->id,
            'product_name' => 'Station parcel',
            'quantity' => 1,
            'amount' => 100,
            'commission' => 10,
            'status' => $status,
            'tracking_status' => 'Received and scanned at sorting center',
        ]);
    }

    private function riderFor(LogisticsBranch $station, Order $order): User
    {
        $courier = $this->user('courier');
        $assignment = BranchRider::create(['branch_id' => $station->id, 'user_id' => $courier->id, 'status' => 'active']);
        RiderBarangay::create([
            'branch_rider_id' => $assignment->id,
            'barangay_id' => $order->destination_barangay_id,
            'is_primary' => true,
        ]);

        return $courier;
    }

    private function scanned(Order $order, LogisticsBranch $station): void
    {
        ParcelScan::create([
            'order_id' => $order->id,
            'scanned_by' => $this->logistics->id,
            'scan_type' => 'sorting_center_received',
            'location' => $station->name,
            'scanned_at' => now(),
        ]);
    }

    private function operatorFields(string $email): array
    {
        return [
            'operator_first_name' => 'Hub',
            'operator_last_name' => 'Operator',
            'operator_sex' => 'Female',
            'operator_email' => $email,
            'operator_password' => 'password123',
            'operator_password_confirmation' => 'password123',
            'operator_contact_no' => '09171234567',
            'operator_birthday' => '1990-01-01',
        ];
    }

    private function user(string $role): User
    {
        static $number = 0;
        $number++;

        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => "Station{$number}",
            'email' => "{$role}{$number}@station-hubs.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion',
        ]);
    }
}
