<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Zone;
use App\Models\User;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\CompanySettlement;
use App\Pickrush\Services\Hq\Finance;
use App\Pickrush\Services\Hq\NetworkMetrics;
use App\Pickrush\Services\Hq\PicksellSettlement;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\Station\CashHandling;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Services\Station\RemittanceDeadline;
use App\Pickrush\Services\Station\RiderTaskService;
use App\Pickrush\Services\Station\ZoneSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StationOperationsTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Hub $sanJose;

    private Staff $manager;

    private Staff $dispatcher;

    private Staff $sanJoseManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        DB::table('geo_barangays')->insert([
            ['code' => '041029002', 'municipality_code' => '041029000', 'name' => 'Altura Matanda'],
            ['code' => '041029003', 'municipality_code' => '041029000', 'name' => 'Bagbag'],
            ['code' => '041005001', 'municipality_code' => '041005000', 'name' => 'Aguila'],
            ['code' => '041005002', 'municipality_code' => '041005000', 'name' => 'Anus'],
        ]);
        $this->tanauan = $this->station('041029000');
        $this->sanJose = $this->station('041005000');
        $this->manager = $this->staff(StaffRole::HubManager, $this->tanauan);
        $this->dispatcher = $this->staff(StaffRole::Dispatcher, $this->tanauan);
        $this->sanJoseManager = $this->staff(StaffRole::HubManager, $this->sanJose);
    }

    private function rider(Hub $station, array $overrides = []): Rider
    {
        static $n = 0;
        $n++;

        $rider = Rider::withoutGlobalScopes()->create(array_merge([
            'station_hub_id' => $station->id, 'full_name' => "Rider {$n}", 'mobile_no' => '0918'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle', 'status' => 'active', 'shift_status' => 'on_shift', 'last_seen_at' => now(),
            'cod_limit' => 10000, 'max_parcels' => 30, 'created_by' => $this->manager->id,
        ], $overrides));
        \App\Pickrush\Models\RiderToken::issue($rider);   // logged in to the rider app

        return $rider;
    }

    /** Booked from Tanauan to San Jose (same province) unless overridden. */
    private function parcel(array $overrides = []): Parcel
    {
        return app(ParcelRegistrar::class)->register(array_merge([
            'shipper_name' => 'Tote House', 'pickup_address' => 'Altura Bata, Tanauan',
            'pickup_barangay_code' => '041029001', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Ana Reyes', 'consignee_mobile' => '09171234567', 'delivery_address' => 'Aguila, San Jose',
            'delivery_barangay_code' => '041005001', 'delivery_municipality_code' => '041005000', 'cod_amount' => 1500,
        ], $overrides));
    }

    private function as(Staff $staff): static
    {
        return $this->actingAs($staff, 'pickrush');
    }

    /** Carry a parcel from Tanauan to San Jose through the Batangas hub, ready for dispatch. */
    private function bringToSanJose(Parcel $parcel): Parcel
    {
        $manifests = app(ManifestService::class);
        $batangas = $this->tanauan->parent;
        $sorter = $this->staff(StaffRole::Sorter, $batangas);

        $this->as($this->dispatcher)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no]);
        $out = $manifests->create($this->tanauan, $batangas, $this->dispatcher);
        $manifests->add($out, $parcel->tracking_no);
        $this->dispatchTrip($out, $this->dispatcher);

        $this->as($sorter);
        $manifests->receive($out, $parcel->tracking_no, $sorter);
        $down = $manifests->create($batangas, $this->sanJose, $sorter);
        $manifests->add($down, $parcel->tracking_no);
        $this->dispatchTrip($down, $sorter);

        $this->as($this->sanJoseManager)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])->assertSessionHasNoErrors();

        return $parcel->fresh();
    }

    // First mile ------------------------------------------------------------------------------

    public function test_pickup_rider_or_walk_in_brings_the_parcel_into_the_origin_station(): void
    {
        $rider = $this->rider($this->tanauan);
        $picked = $this->parcel();
        $walkIn = $this->parcel();

        $this->as($this->dispatcher)->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => [$picked->id, $walkIn->id], 'rider_id' => $rider->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Assignment::where('type', 'pickup')->count());
        $this->assertSame(ParcelStatus::WaitingPickup, $picked->fresh()->current_status, 'a pickup task does not move the parcel');

        app(RiderTaskService::class)->pickUp(Assignment::where('parcel_id', $picked->id)->sole());
        $this->assertSame(ParcelStatus::PickedUp, $picked->fresh()->current_status);

        foreach ([$picked, $walkIn] as $parcel) {
            $this->as($this->dispatcher)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => strtolower($parcel->tracking_no)])
                ->assertSessionHas('success', fn ($m) => str_contains($m, 'Put it on the next trip to Batangas Province Hub'));
        }
        $this->assertSame([ParcelStatus::AtOriginHub, ParcelStatus::AtOriginHub], [$picked->fresh()->current_status, $walkIn->fresh()->current_status]);
        $this->assertSame('cancelled', Assignment::where('parcel_id', $walkIn->id)->sole()->status, 'the walk-in made the pickup task unnecessary');

        $this->as($this->sanJoseManager)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $this->parcel()->tracking_no])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'should be dropped off at Tanauan City Station'));
    }

    // Trips -----------------------------------------------------------------------------------

    public function test_trips_carry_parcels_hub_to_hub_and_flag_missing_ones(): void
    {
        $arrives = $this->parcel();
        $lost = $this->parcel();
        foreach ([$arrives, $lost] as $p) {
            $this->as($this->dispatcher)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $p->tracking_no]);
        }

        $this->as($this->dispatcher)->post($this->pickrushUrl('/station/trips'))->assertRedirect();
        $trip = Manifest::sole();
        $this->as($this->dispatcher)->post($this->pickrushUrl("/station/trips/{$trip->manifest_no}/parcels/all"))->assertSessionHas('success', '2 parcels added.');
        $this->as($this->dispatcher)->post($this->pickrushUrl("/station/trips/{$trip->manifest_no}/parcels"), ['tracking_no' => $arrives->tracking_no])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'already on a trip'));
        [$truck, $driver] = $this->fleet($this->tanauan);
        $this->as($this->dispatcher)->post($this->pickrushUrl("/station/trips/{$trip->manifest_no}/dispatch"), ['vehicle_id' => $truck->id, 'driver_id' => $driver->id])
            ->assertSessionHasNoErrors();

        $this->assertSame([ParcelStatus::InTransitToProvince, $this->tanauan->parent_hub_id], [$arrives->fresh()->current_status, $arrives->fresh()->next_hub_id]);
        $this->assertSame(['dispatched', $truck->plate_no, $driver->full_name], [$trip->fresh()->status, $trip->fresh()->vehicle_plate, $trip->fresh()->driver_name]);

        // Batangas checks the trip in (the province portal comes next; the service is shared).
        $sorter = $this->staff(StaffRole::Sorter, $this->tanauan->parent);
        $this->as($sorter);
        app(ManifestService::class)->receive($trip, $arrives->tracking_no, $sorter);
        $this->assertSame(1, app(ManifestService::class)->close($trip->fresh(), $sorter));

        $this->assertSame(ParcelStatus::AtProvinceHub, $arrives->fresh()->current_status);
        $this->assertSame($this->sanJose->id, $arrives->fresh()->next_hub_id);
        $this->assertSame(['scanned', 'missing'], $trip->items()->orderBy('id')->pluck('scan_status')->all());
    }

    public function test_station_receives_from_the_province_hub_against_the_trip_list(): void
    {
        $parcel = $this->bringToSanJose($this->parcel());

        $this->assertSame([ParcelStatus::ArrivedAtStation, $this->sanJose->id, null], [$parcel->current_status, $parcel->current_hub_id, $parcel->next_hub_id]);
        $this->assertSame('scanned', Manifest::where('to_hub_id', $this->sanJose->id)->sole()->items()->sole()->scan_status);
        $this->assertStringContainsString('Arrived at San Jose Station', $parcel->events()->reorder()->latest('id')->first()->headline());
    }

    // Dispatch --------------------------------------------------------------------------------

    public function test_zone_suggests_the_default_rider_then_the_backup(): void
    {
        $default = $this->rider($this->sanJose);
        $backup = $this->rider($this->sanJose);
        $this->as($this->sanJoseManager)->post($this->pickrushUrl('/station/zones'), [
            'name' => 'Zone A', 'default_rider_id' => $default->id, 'backup_rider_id' => $backup->id, 'barangays' => ['041005001'],
        ])->assertRedirect();
        $parcel = $this->bringToSanJose($this->parcel());
        $suggest = fn () => app(ZoneSuggester::class)->suggest($parcel->fresh(), $this->sanJose);

        $this->assertTrue($default->is($suggest()['rider']));
        $default->update(['shift_status' => 'off_duty']);
        $this->assertTrue($backup->is($suggest()['rider']));
        $backup->update(['status' => 'suspended']);
        $this->assertNull($suggest()['rider']);

        $noZone = $this->bringToSanJose($this->parcel(['delivery_barangay_code' => '041005002']));
        $this->assertStringContainsString('No zone', app(ZoneSuggester::class)->suggest($noZone, $this->sanJose)['note']);
        $this->as($this->sanJoseManager)->get($this->pickrushUrl('/station/dispatch?view=no_zone'))->assertOk()->assertSee($noZone->tracking_no)->assertDontSee($parcel->tracking_no);
    }

    public function test_bulk_assign_to_suggested_riders_moves_parcels_and_orders(): void
    {
        $rider = $this->rider($this->sanJose);
        Zone::withoutGlobalScopes()->create(['station_hub_id' => $this->sanJose->id, 'name' => 'All', 'default_rider_id' => $rider->id])
            ->barangayLinks()->createMany([['barangay_code' => '041005001'], ['barangay_code' => '041005002']]);
        $a = $this->bringToSanJose($this->parcel());
        $b = $this->bringToSanJose($this->parcel(['delivery_barangay_code' => '041005002']));

        $this->as($this->sanJoseManager)->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', '2 parcel(s) assigned.');

        $this->assertSame([ParcelStatus::AssignedToRider, ParcelStatus::AssignedToRider], [$a->fresh()->current_status, $b->fresh()->current_status]);
        $this->assertSame(2, $rider->openAssignments()->count());

        $task = Assignment::where('parcel_id', $a->id)->sole();
        $this->as($this->sanJoseManager)->post($this->pickrushUrl("/station/assignments/{$task->id}/unassign"), ['reason' => 'Rider went home sick'])->assertSessionHasNoErrors();
        $this->assertSame(['reassigned', ParcelStatus::ArrivedAtStation], [$task->fresh()->status, $a->fresh()->current_status]);
    }

    public function test_dispatch_rules_cod_limit_variance_and_attempt_limit(): void
    {
        $small = $this->rider($this->sanJose, ['cod_limit' => 1000]);
        $parcel = $this->bringToSanJose($this->parcel());
        $dispatch = app(DispatchService::class);
        $this->as($this->sanJoseManager);

        $this->expectRefusal(fn () => $dispatch->assign($parcel, $small, $this->sanJoseManager), 'above the ₱1,000.00 limit');

        $other = $this->rider($this->sanJose);
        CodRemittance::withoutGlobalScopes()->create(['rider_id' => $other->id, 'station_hub_id' => $this->sanJose->id, 'remit_date' => today(),
            'expected_amount' => 500, 'received_amount' => 400, 'variance' => -100, 'status' => 'disputed', 'received_by' => $this->sanJoseManager->id]);
        $this->expectRefusal(fn () => $dispatch->assign($parcel, $other, $this->sanJoseManager), 'unresolved cash variance');

        $tanauanRider = $this->rider($this->tanauan);
        $this->expectRefusal(fn () => $dispatch->assign($parcel, $tanauanRider, $this->sanJoseManager), 'belongs to another station');

        $parcel->forceFill(['attempt_count' => 3])->save();
        $this->expectRefusal(fn () => $dispatch->assign($parcel, $this->rider($this->sanJose), $this->sanJoseManager), 'attempt limit');
    }

    // Rider tasks -----------------------------------------------------------------------------

    public function test_delivery_needs_the_exact_cod_and_failed_attempts_need_a_reason(): void
    {
        $rider = $this->rider($this->sanJose);
        $applicant = $this->rider($this->sanJose, ['status' => 'pending', 'created_by' => null, 'first_name' => 'Ana', 'last_name' => 'Reyes', 'applied_at' => now()]);
        $parcel = $this->bringToSanJose($this->parcel());
        $this->as($this->sanJoseManager);
        $tasks = app(RiderTaskService::class);

        $task = app(DispatchService::class)->assign($parcel, $rider, $this->sanJoseManager);
        $tasks->startDelivery($task);
        $this->expectRefusal(fn () => $tasks->fail($task->fresh(), 'other', null, 'proof/door.jpg'), 'Write a note');
        $tasks->fail($task->fresh(), 'buyer_unavailable', null, 'proof/door.jpg', ['idempotency_key' => 'k1']);
        $tasks->fail($task->fresh(), 'buyer_unavailable', null, 'proof/door.jpg', ['idempotency_key' => 'k1']); // phone retried
        $this->assertSame([ParcelStatus::DeliveryFailed, 1], [$parcel->fresh()->current_status, $parcel->fresh()->attempt_count]);

        $retry = app(DispatchService::class)->assign($parcel->fresh(), $rider, $this->sanJoseManager);
        $this->assertSame(2, $retry->attempt_no);
        $tasks->startDelivery($retry);
        $this->expectRefusal(fn () => $tasks->deliver($retry->fresh(), 1000, 'proof/signed.jpg'), 'collect exactly that amount');
        $tasks->deliver($retry->fresh(), 1500, 'proof/signed.jpg');

        $this->assertSame(ParcelStatus::Delivered, $parcel->fresh()->current_status);
        $this->assertSame('1500.00', $retry->fresh()->cod_collected);
    }

    // Cash ------------------------------------------------------------------------------------

    public function test_the_rider_keeps_their_pay_and_staff_confirm_the_rest(): void
    {
        $rider = $this->rider($this->sanJose);
        $this->as($this->sanJoseManager);
        $parcel = $this->bringToSanJose($this->parcel());
        $parcel->forceFill(['rider_pay' => 40])->save();
        $task = app(DispatchService::class)->assign($parcel->fresh(), $rider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->deliver($task->fresh(), 1500, 'proof.jpg');

        $this->assertSame(['cod' => 1500.0, 'pay' => 40.0, 'to_remit' => 1460.0], array_diff_key(app(CashHandling::class)->breakdown($rider), ['earning_ids' => 0]));
        $this->as($this->sanJoseManager)->get($this->pickrushUrl('/station/cash'))->assertSeeInOrder(['₱1,500.00', '−₱40.00', 'Confirm ₱1,460.00 received']);
        $url = $this->pickrushUrl("/station/cash/riders/{$rider->id}");
        $this->as($this->sanJoseManager)->post($url, ['expected_amount' => '1500.00'])->assertSessionHasErrors();
        $this->assertSame(0, CodRemittance::count(), 'a total different from the one shown is not confirmed');

        $this->as($this->sanJoseManager)->post($url, ['expected_amount' => '1460.00'])->assertSessionHasNoErrors();
        $remittance = CodRemittance::sole();
        $this->assertSame([CodRemittance::AWAITING_RIDER, '1460.00', '40.00', '1460.00', '0.00'],
            [$remittance->status, $remittance->expected_amount, $remittance->rider_pay_kept, $remittance->received_amount, $remittance->variance]);
        $this->assertSame(0, DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->whereNull('settled_at')->count(), 'the pay is settled once');
        $this->assertSame(0.0, app(CashHandling::class)->stationCashOnHand($this->sanJose), 'not available to remit until the rider confirms');
        $this->assertDatabaseHas('pr_rider_notifications', ['rider_id' => $rider->id, 'type' => 'cash_count']);

        app(CashHandling::class)->confirmCount($remittance, $rider);
        $this->assertSame('reconciled', $remittance->fresh()->status);
        $this->assertSame(1460.0, app(CashHandling::class)->stationCashOnHand($this->sanJose));
    }

    public function test_a_rider_who_rejects_the_count_gets_the_cash_counted_again(): void
    {
        $rider = $this->rider($this->sanJose);
        $other = $this->rider($this->sanJose);
        $this->as($this->sanJoseManager);
        $parcel = $this->bringToSanJose($this->parcel());
        $parcel->forceFill(['rider_pay' => 40])->save();
        $task = app(DispatchService::class)->assign($parcel->fresh(), $rider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->deliver($task->fresh(), 1500, 'proof.jpg');

        // Staff record ₱500 less than the rider handed over.
        $count = app(CashHandling::class)->recordRiderRemittance($rider, 960, $this->sanJoseManager);
        $this->assertSame(0.0, app(CashHandling::class)->expectedFrom($rider));
        $this->expectRefusal(fn () => app(CashHandling::class)->recordRiderRemittance($rider, 1460, $this->sanJoseManager), 'has not confirmed the last count');
        $next = $this->bringToSanJose($this->parcel());
        $this->expectRefusal(fn () => app(DispatchService::class)->assign($next, $rider, $this->sanJoseManager), 'has not confirmed the station\'s count');

        // withHeaders() sets the token on the shared test client, so each rider's requests set their own.
        $this->withHeaders(['Authorization' => 'Bearer '.RiderToken::issue($other), 'Accept' => 'application/json'])
            ->postJson("/api/rider/cash-counts/{$count->id}/confirm")->assertNotFound();
        $app = $this->withHeaders(['Authorization' => 'Bearer '.RiderToken::issue($rider), 'Accept' => 'application/json']);
        $app->getJson('/api/rider/earnings')->assertOk()
            ->assertJsonPath('cod.pending_count.id', $count->id)
            ->assertJsonPath('cod.pending_count.counted', 960)
            ->assertJsonPath('cod.pending_count.difference', -500);
        $app->postJson("/api/rider/cash-counts/{$count->id}/reject")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $app->postJson("/api/rider/cash-counts/{$count->id}/reject", ['reason' => 'I handed over ₱1,460, not ₱960'])->assertOk();
        $app->postJson("/api/rider/cash-counts/{$count->id}/confirm")->assertUnprocessable()->assertJsonPath('message', 'You already answered this count.');

        // The count is void: the cash is with the rider again, the pay unsettled, and HQ sees it.
        $this->assertSame([CodRemittance::REJECTED, 'I handed over ₱1,460, not ₱960'], [$count->fresh()->status, $count->fresh()->rider_note]);
        $this->assertSame(1460.0, app(CashHandling::class)->expectedFrom($rider));
        $this->assertSame(1, DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->whereNull('settled_at')->count());
        $this->as($this->sanJoseManager)->get($this->pickrushUrl('/station/cash'))->assertSee('Counts riders rejected')->assertSee('I handed over ₱1,460, not ₱960');
        $this->as($this->staff(StaffRole::Finance))->get($this->pickrushUrl('/hq/alerts'))->assertSee('Cash counts riders rejected');

        // Counted again together: confirmed and available to remit.
        $recount = app(CashHandling::class)->recordRiderRemittance($rider, 1460, $this->sanJoseManager);
        $this->as($this->sanJoseManager)->get($this->pickrushUrl('/station/cash'))->assertDontSee('Counts riders rejected');
        $this->as($this->staff(StaffRole::Finance))->get($this->pickrushUrl('/hq/alerts'))->assertSee('· counted again')->assertDontSee('not counted again yet');   // HQ keeps the history
        $app->postJson("/api/rider/cash-counts/{$recount->id}/confirm")->assertOk()->assertJsonPath('status', 'reconciled');
        $this->assertSame(1460.0, app(CashHandling::class)->stationCashOnHand($this->sanJose));
        app(DispatchService::class)->assign($next->fresh(), $rider, $this->sanJoseManager);
    }

    public function test_only_the_pay_for_the_deliveries_being_remitted_is_kept(): void
    {
        $rider = $this->rider($this->sanJose);
        $this->as($this->sanJoseManager);
        // Earlier pickup pay, never part of any cash.
        foreach (range(1, 16) as $i) {
            DB::table('pr_rider_earnings')->insert(['rider_id' => $rider->id, 'parcel_id' => $this->parcel()->id, 'kind' => 'pickup',
                'amount' => 15, 'earned_at' => now()->subDays(20), 'created_at' => now()]);
        }

        $parcel = $this->bringToSanJose($this->parcel(['cod_amount' => 500]));
        $parcel->forceFill(['rider_pay' => 15])->save();
        $task = app(DispatchService::class)->assign($parcel->fresh(), $rider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->deliver($task->fresh(), 500, 'proof.jpg');

        $this->assertSame(['cod' => 500.0, 'pay' => 15.0, 'to_remit' => 485.0], array_diff_key(app(CashHandling::class)->breakdown($rider), ['earning_ids' => 0]),
            'the ₱240 of old pickup pay is not taken from this cash');
        app(CashHandling::class)->recordRiderRemittance($rider, null, $this->sanJoseManager);
        $this->assertSame(16, DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->where('kind', 'pickup')->whereNull('settled_at')->count());
    }

    public function test_cash_remittance_variance_blocks_dispatch_until_resolved_then_goes_to_hq(): void
    {
        $rider = $this->rider($this->sanJose);
        $this->as($this->sanJoseManager);
        $first = $this->bringToSanJose($this->parcel());
        $task = app(DispatchService::class)->assign($first, $rider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->deliver($task->fresh(), 1500, 'proof.jpg');

        $expected = app(CashHandling::class)->expectedFrom($rider);
        $this->assertLessThan(1500.0, $expected, 'the rider keeps their pay');
        $this->as($this->sanJoseManager)->post($this->pickrushUrl("/station/cash/riders/{$rider->id}"), ["received_amount" => $expected - 100])->assertRedirect()->assertSessionHasNoErrors();
        $remittance = CodRemittance::sole();
        app(CashHandling::class)->confirmCount($remittance, $rider);
        $this->assertSame(['disputed', '-100.00'], [$remittance->fresh()->status, $remittance->fresh()->variance]);
        $this->assertSame(0.0, app(CashHandling::class)->expectedFrom($rider), 'the cash is counted once');

        $next = $this->bringToSanJose($this->parcel());
        $this->expectRefusal(fn () => app(DispatchService::class)->assign($next, $rider, $this->sanJoseManager), 'unresolved cash variance');

        $this->as($this->staff(StaffRole::Dispatcher, $this->sanJose))->post($this->pickrushUrl("/station/cash/remittances/{$remittance->id}/resolve"), ['resolution' => 'Short ₱100, deducted from earnings'])
            ->assertForbidden();
        $this->as($this->sanJoseManager)->post($this->pickrushUrl("/station/cash/remittances/{$remittance->id}/resolve"), ['resolution' => 'Short ₱100, deducted from earnings'])
            ->assertSessionHasNoErrors();
        app(DispatchService::class)->assign($next->fresh(), $rider, $this->sanJoseManager);

        Storage::fake('local');
        $this->as($this->sanJoseManager)->post($this->pickrushUrl('/station/cash/remit'), ['expected_amount' => $expected - 100, 'reference_no' => 'BDO-0001', 'slip' => $this->slip()])
            ->assertSessionHasNoErrors();
        $this->assertSame(0.0, app(CashHandling::class)->stationCashOnHand($this->sanJose));
        $this->assertNotNull(CodRemittance::withoutGlobalScopes()->sole()->hub_remittance_id, 'the rider cash is now part of the remittance to HQ');
    }

    public function test_a_deposit_to_hq_needs_its_slip_and_a_reference_used_only_once(): void
    {
        Storage::fake('local');
        $rider = $this->rider($this->sanJose);
        $remit = $this->pickrushUrl('/station/cash/remit');
        $cash = function (float $cod) use ($rider) {
            $parcel = $this->bringToSanJose($this->parcel(['cod_amount' => $cod]));
            $task = app(DispatchService::class)->assign($parcel, $rider, $this->sanJoseManager);
            app(RiderTaskService::class)->startDelivery($task);
            app(RiderTaskService::class)->deliver($task->fresh(), $cod, 'proof.jpg');
            $count = app(CashHandling::class)->recordRiderRemittance($rider, null, $this->sanJoseManager);
            app(CashHandling::class)->confirmCount($count, $rider);

            return (float) $count->received_amount;
        };
        $this->as($this->sanJoseManager);
        $first = $cash(1000);
        $this->as($this->sanJoseManager)->get($this->pickrushUrl('/station/cash'))->assertSeeInOrder([
            'Collected, not yet remitted to HQ', $rider->full_name, 'Total to remit (1 remittance)', 'Remit ₱'.number_format($first, 2).' to HQ']);

        $this->as($this->sanJoseManager)->post($remit, ['expected_amount' => $first, 'reference_no' => 'BDO-0042'])->assertSessionHasErrors('slip');
        $this->as($this->sanJoseManager)->post($remit, ['expected_amount' => $first, 'reference_no' => 'BDO-0042', 'slip' => UploadedFile::fake()->create('slip.docx', 10)])
            ->assertSessionHasErrors('slip');
        // The page showed an older total: more cash was counted since, so it is checked again.
        $this->as($this->sanJoseManager)->post($remit, ['expected_amount' => $first - 300, 'reference_no' => 'BDO-0042', 'slip' => $this->slip()])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'The cash to remit changed to ₱'.number_format($first, 2)));
        $this->assertSame(0, HubRemittance::withoutGlobalScopes()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('pickrush/deposit-slips'), 'a refused deposit leaves no slip behind');

        // A typed amount is ignored: the remittance is always the system total.
        $this->as($this->sanJoseManager)->post($remit, ['expected_amount' => $first, 'amount' => 1, 'reference_no' => 'BDO-0042', 'slip' => $this->slip()])->assertSessionHasNoErrors();
        $hub = HubRemittance::withoutGlobalScopes()->sole();
        $this->assertSame([(string) number_format($first, 2, '.', ''), '0.00'], [$hub->remitted_amount, $hub->variance]);
        $this->assertSame('BDO0042', $hub->reference_key);
        Storage::disk('local')->assertExists($hub->slip_path);
        $this->as($this->sanJoseManager)->get($this->pickrushUrl("/station/cash/deposits/{$hub->id}/slip"))->assertOk();

        // The same deposit reference again, however it is typed, is refused.
        $second = $cash(800);
        $this->as($this->sanJoseManager)->post($remit, ['expected_amount' => $second, 'reference_no' => 'bdo 0042', 'slip' => $this->slip()])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'was already used for a remittance'));
        $this->as($this->sanJoseManager)->post($remit, ['expected_amount' => $second, 'reference_no' => 'BDO-0043', 'slip' => $this->slip()])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, HubRemittance::withoutGlobalScopes()->count());

        // Another station cannot open this station's slip.
        $this->as($this->manager)->get($this->pickrushUrl("/station/cash/deposits/{$hub->id}/slip"))->assertNotFound();
    }

    public function test_a_station_that_does_not_remit_by_the_deadline_stops_dispatching_deliveries(): void
    {
        Storage::fake('local');
        config(['pickrush.policies.remittance_cutoff' => '20:00', 'pickrush.policies.remittance_grace_hours' => 16, 'pickrush.policies.remittance_cycle' => 'daily']);
        $this->travelTo(Carbon::parse('2026-11-10 10:00'));
        $rider = $this->rider($this->sanJose);
        $this->as($this->sanJoseManager);
        $first = $this->bringToSanJose($this->parcel());
        $task = app(DispatchService::class)->assign($first, $rider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->deliver($task->fresh(), 1500, 'proof.jpg');
        app(CashHandling::class)->confirmCount(app(CashHandling::class)->recordRiderRemittance($rider, null, $this->sanJoseManager), $rider);
        $deadline = fn () => app(RemittanceDeadline::class)->for($this->sanJose);
        $this->assertSame(['ok', '2026-11-10 20:00', '2026-11-11 12:00'],
            [$deadline()['state'], $deadline()['due_at']->format('Y-m-d H:i'), $deadline()['block_at']->format('Y-m-d H:i')]);
        $assign = function () use ($rider) {
            $rider->forceFill(['last_seen_at' => now()])->save();   // still on shift after the clock moved

            return app(DispatchService::class)->assign($this->bringToSanJose($this->parcel()), $rider->fresh(), $this->sanJoseManager);
        };

        // Past the cutoff: warned, still dispatching.
        $this->travelTo(Carbon::parse('2026-11-10 21:00'));
        $this->assertSame('overdue', $deadline()['state']);
        $this->as($this->sanJoseManager)->get($this->pickrushUrl('/station/cash'))->assertSee('was due at HQ on Nov 10, 8:00 PM')->assertSee('Remit it by Nov 11, 12:00 PM');
        app(DispatchService::class)->unassign($assign(), $this->sanJoseManager, 'testing');

        // Past the grace hours: no deliveries, pickups still go, HQ sees it.
        $this->travelTo(Carbon::parse('2026-11-11 12:30'));
        $this->assertSame('blocked', $deadline()['state']);
        $this->expectRefusal($assign, 'Remit it on the Cash page before dispatching deliveries');
        $rider->forceFill(['last_seen_at' => now()])->save();
        $pickup = $this->parcel(['pickup_barangay_code' => '041005002', 'pickup_municipality_code' => '041005000']);
        $this->assertSame('pickup', app(DispatchService::class)->assign($pickup, $rider->fresh(), $this->sanJoseManager)->type);
        $this->as($this->staff(StaffRole::Finance))->get($this->pickrushUrl('/hq/alerts'))->assertSee('Stations late remitting cash')->assertSee('deliveries stopped');

        // Remitted: deliveries go again.
        $this->as($this->sanJoseManager)->post($this->pickrushUrl('/station/cash/remit'), ['expected_amount' => app(CashHandling::class)->stationCashOnHand($this->sanJose), 'reference_no' => 'BDO-7001', 'slip' => $this->slip()])
            ->assertSessionHasNoErrors();
        $this->assertSame('none', $deadline()['state']);
        $assign();
    }

    public function test_a_disputed_deposit_waits_for_the_station_to_top_up_or_contest_it(): void
    {
        Storage::fake('local');
        config(['pickrush.policies.remittance_cutoff' => '20:00', 'pickrush.policies.remittance_grace_hours' => 16, 'pickrush.policies.remittance_cycle' => 'daily']);
        $this->travelTo(Carbon::parse('2026-11-10 09:00'));
        $rider = $this->rider($this->sanJose);
        $finance = $this->staff(StaffRole::Finance);
        $remitAll = function (string $reference) use ($rider): HubRemittance {
            $this->as($this->sanJoseManager);
            $rider->forceFill(['last_seen_at' => now()])->save();
            $parcel = $this->bringToSanJose($this->parcel(['cod_amount' => 1000]));
            $task = app(DispatchService::class)->assign($parcel, $rider->fresh(), $this->sanJoseManager);
            app(RiderTaskService::class)->startDelivery($task);
            app(RiderTaskService::class)->deliver($task->fresh(), 1000, 'proof.jpg');
            app(CashHandling::class)->confirmCount(app(CashHandling::class)->recordRiderRemittance($rider, null, $this->sanJoseManager), $rider);

            return app(CashHandling::class)->submitHubRemittance($this->sanJoseManager, $reference, $this->slip())->fresh();
        };
        $deadline = fn () => app(RemittanceDeadline::class)->for($this->sanJose);

        // HQ finds ₱300 missing at the bank.
        $first = $remitAll('BDO-1001');
        $declared = (float) $first->remitted_amount;
        app(Finance::class)->confirm($first, $finance, false, 'Bank shows ₱300 less', $declared - 300);
        $this->assertSame(['disputed', 300.0], [$first->fresh()->status, $first->fresh()->shortfall()]);
        $this->as($this->sanJoseManager)->get($this->pickrushUrl('/station/cash'))
            ->assertSee('HQ disputed your deposit ref BDO-1001')->assertSee('Bank shows ₱300 less')->assertSee('Confirm ₱300.00 deposited');
        $this->as($finance)->get($this->pickrushUrl('/hq/alerts'))->assertSee('Disputed with stations')->assertSee('₱300.00 short');
        $this->assertSame(300.0, app(NetworkMetrics::class)->cashOutstanding()['disputed_with_stations']);

        // Unanswered, it is cash due at HQ: overdue at once, deliveries stop after the grace hours.
        $this->assertSame(['overdue', 300.0], [$deadline()['state'], $deadline()['amount']]);
        $this->travelTo(Carbon::parse('2026-11-11 02:00'));
        $this->assertSame('blocked', $deadline()['state']);
        $rider->forceFill(['last_seen_at' => now()])->save();
        $this->expectRefusal(fn () => app(DispatchService::class)->assign($this->bringToSanJose($this->parcel()), $rider->fresh(), $this->sanJoseManager), 'Remit it on the Cash page');

        // The station deposits the shortfall; HQ confirms it and the dispute is settled.
        $topUpUrl = $this->pickrushUrl("/station/cash/deposits/{$first->id}/top-up");
        $this->as($this->sanJoseManager)->post($topUpUrl, ['shortfall' => 200, 'reference_no' => 'BDO-1002', 'slip' => $this->slip()])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'The shortfall is now ₱300.00'));
        $this->as($this->sanJoseManager)->post($topUpUrl, ['shortfall' => 300, 'reference_no' => 'BDO-1001', 'slip' => $this->slip()])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'already used'));
        $this->as($this->sanJoseManager)->post($topUpUrl, ['shortfall' => 300, 'reference_no' => 'BDO-1002', 'slip' => $this->slip()])->assertSessionHasNoErrors();
        $topUp = HubRemittance::withoutGlobalScopes()->where('reference_no', 'BDO-1002')->sole();
        $this->assertSame([$first->id, '300.00', 'submitted'], [$topUp->settles_id, $topUp->remitted_amount, $topUp->status]);
        $this->assertSame('none', $deadline()['state'], 'answered: the top-up waits for HQ, not the station');
        $this->as($this->sanJoseManager)->post($topUpUrl, ['shortfall' => 300, 'reference_no' => 'BDO-1003', 'slip' => $this->slip()])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'already waiting for HQ'));
        $this->as($finance)->get($this->pickrushUrl('/hq/finance'))->assertSee('Top-up')->assertSee('for disputed ref BDO-1001');
        app(Finance::class)->confirm($topUp, $finance, true, null, 300);
        $this->assertSame(HubRemittance::SETTLED, $first->fresh()->status);
        $this->assertSame(0.0, app(NetworkMetrics::class)->cashOutstanding()['disputed_with_stations']);
        $this->assertSame((float) $declared, app(NetworkMetrics::class)->summary(now()->subDays(3), now())['cod_remitted'], 'the bank amount and the top-up make the full deposit');

        // Another deposit HQ disputes by mistake: the station contests it and HQ confirms after checking again.
        $second = $remitAll('BDO-2001');
        app(Finance::class)->confirm($second, $finance, false, 'Not on the statement', 0);
        $this->as($this->sanJoseManager)->post($this->pickrushUrl("/station/cash/deposits/{$second->id}/contest"), ['explanation' => 'Short'])->assertSessionHasErrors('explanation');
        $this->as($this->sanJoseManager)->post($this->pickrushUrl("/station/cash/deposits/{$second->id}/contest"), [
            'explanation' => 'Deposited at 7:58 PM; the bank posts it the next morning.', 'evidence' => $this->slip()])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $second->fresh()->status);
        $this->assertSame('none', $deadline()['state']);
        $this->as($finance)->get($this->pickrushUrl('/hq/finance'))->assertSee('Contested')->assertSee('the bank posts it the next morning');
        $this->as($finance)->get($this->pickrushUrl("/hq/finance/remittances/{$second->id}/evidence"))->assertOk();
        app(Finance::class)->confirm($second->fresh(), $finance, true, null, (float) $second->remitted_amount);
        $this->assertSame('confirmed', $second->fresh()->status);

        // Only the station that made the deposit answers for it.
        $third = $remitAll('BDO-3001');
        app(Finance::class)->confirm($third, $finance, false, 'Short', 0);
        $this->as($this->manager)->post($this->pickrushUrl("/station/cash/deposits/{$third->id}/contest"), ['explanation' => 'Not ours to answer at all'])->assertNotFound();
    }

    public function test_the_company_settles_with_picksell_what_its_hq_received_and_picksell_confirms_it(): void
    {
        Storage::fake('local');
        config(['pickrush.policies.settlement_days' => 1, 'pickrush.policies.settlement_grace_hours' => 24]);
        $this->travelTo(Carbon::parse('2026-11-10 09:00'));
        $rider = $this->rider($this->sanJose);
        $finance = $this->staff(StaffRole::Finance);
        $picksell = app(PicksellSettlement::class);
        $company = Company::findOrFail(Company::defaultId());
        $this->as($this->sanJoseManager);
        foreach ([[1000, 50, 30], [1500, 60, 40]] as [$cod, $pay, $share]) {
            $parcel = $this->bringToSanJose($this->parcel(['cod_amount' => $cod, 'source' => 'picksell']));
            $parcel->forceFill(['rider_pay' => $pay, 'logistics_share' => $share])->save();
            $task = app(DispatchService::class)->assign($parcel->fresh(), $rider, $this->sanJoseManager);
            app(RiderTaskService::class)->startDelivery($task);
            app(RiderTaskService::class)->deliver($task->fresh(), $cod, 'proof.jpg');
        }
        app(CashHandling::class)->confirmCount(app(CashHandling::class)->recordRiderRemittance($rider, null, $this->sanJoseManager), $rider);
        $hub = app(CashHandling::class)->submitHubRemittance($this->sanJoseManager, 'BDO-5001', $this->slip())->fresh();
        $this->assertSame(0.0, $picksell->owed($company->id)['total'], 'owed only once HQ confirms the cash');

        // HQ confirms the station's cash: now the parcels are owed to PickSell (COD less rider pay and share).
        app(Finance::class)->confirm($hub, $finance, true, null);
        $owed = $picksell->owed($company->id);
        $this->assertSame([2, 920.0 + 1400.0], [$owed['parcels']->count(), $owed['total']]);
        $this->as($finance)->get($this->pickrushUrl('/hq/finance'))->assertSee('Owed to PickSell, not yet settled')->assertSee('Settle ₱2,320.00 with PickSell');

        // Past the deadline and grace: overdue, but PickSell's default carrier stays at checkout.
        $this->travelTo(Carbon::parse('2026-11-12 10:00'));
        $this->assertSame('blocked', $picksell->deadline($company->id)['state']);
        $this->assertFalse($picksell->holdsBack($company));

        // HQ finance settles everything in one deposit; the amount is the system's.
        $store = $this->pickrushUrl('/hq/finance/settlements');
        $this->as($this->staff(StaffRole::Support))->post($store, ['total' => 2320, 'reference_no' => 'BPI-1', 'slip' => $this->slip()])->assertForbidden();
        $this->as($finance)->post($store, ['total' => 2000, 'reference_no' => 'BPI-1', 'slip' => $this->slip()])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'changed to ₱2,320.00'));
        $this->as($finance)->post($store, ['total' => 2320, 'reference_no' => 'BPI-1', 'slip' => $this->slip()])->assertSessionHasNoErrors();
        $settlement = CompanySettlement::withoutGlobalScopes()->sole();
        $this->assertSame(['2320.00', 2, 'submitted'], [$settlement->amount, $settlement->parcel_count, $settlement->status]);
        $this->assertSame([0, 'none'], [$picksell->owedParcels($company->id)->count(), $picksell->deadline($company->id)['state']]);

        // A PickSell admin finds ₱320 missing; the company tops it up and the dispute is settled.
        $admin = User::create(['first_name' => 'Pick', 'last_name' => 'Admin', 'email' => 'settle-admin@picksell.test', 'password' => bcrypt('password'),
            'role' => 'admin', 'status' => 'approved', 'sex' => 'Female', 'contact_no' => '09171234568', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Bahay']);
        $adminUrl = fn (string $path) => rtrim(config('app.url'), '/').$path;   // the marketplace, not the logistics portal
        $this->actingAs($admin)->get($adminUrl('/admin/logistics/settlements'))->assertOk()->assertSee('Settlements to confirm (1)')->assertSee('Confirm ₱2,320.00 received');
        $this->actingAs($admin)->patch($adminUrl("/admin/logistics/settlements/{$settlement->id}/review"), ['decision' => 'dispute'])->assertSessionHasErrors('bank_amount');
        $this->actingAs($admin)->patch($adminUrl("/admin/logistics/settlements/{$settlement->id}/review"), ['decision' => 'dispute', 'bank_amount' => 2000, 'note' => 'Bank shows ₱2,000'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['disputed', 320.0], [$settlement->fresh()->status, $settlement->fresh()->shortfall()]);
        $this->as($finance)->get($this->pickrushUrl('/hq/finance'))->assertSee('PickSell disputed your settlement ref BPI-1')->assertSee('Confirm ₱320.00 deposited');
        $this->as($finance)->post($this->pickrushUrl("/hq/finance/settlements/{$settlement->id}/top-up"), ['shortfall' => 320, 'reference_no' => 'BPI-2', 'slip' => $this->slip()])
            ->assertSessionHasNoErrors();
        $topUp = CompanySettlement::withoutGlobalScopes()->where('settles_id', $settlement->id)->sole();
        $this->actingAs($admin)->patch($adminUrl("/admin/logistics/settlements/{$topUp->id}/review"), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame(CompanySettlement::SETTLED, $settlement->fresh()->status);
        $this->assertTrue($settlement->fresh()->isReceived());

        // Another company past its deadline with an unanswered dispute is not offered at checkout.
        $other = Company::create(['name' => 'Bilis Hatid Express', 'slug' => 'bilis-hatid', 'tracking_prefix' => 'BH', 'status' => 'approved']);
        $late = CompanySettlement::withoutGlobalScopes()->create(['company_id' => $other->id, 'amount' => 500, 'reference_no' => 'BH-1', 'reference_key' => 'BH1',
            'slip_path' => 'x.pdf', 'status' => 'disputed', 'bank_amount' => 0, 'submitted_by' => $finance->id, 'confirmed_at' => now()->subHours(30)]);
        $this->assertTrue($picksell->holdsBack($other));
        $late->update(['status' => 'confirmed']);
        $this->assertFalse($picksell->holdsBack($other));
    }

    public function test_cash_counted_after_the_cutoff_is_due_the_next_day_and_longer_cycles_add_days(): void
    {
        config(['pickrush.policies.remittance_cutoff' => '20:00', 'pickrush.policies.remittance_cycle' => 'daily']);
        $this->assertSame('2026-11-10 20:00', RemittanceDeadline::dueFor(Carbon::parse('2026-11-10 19:59'))->format('Y-m-d H:i'));
        $this->assertSame('2026-11-11 20:00', RemittanceDeadline::dueFor(Carbon::parse('2026-11-10 20:00'))->format('Y-m-d H:i'));
        config(['pickrush.policies.remittance_cycle' => 'every_two_days']);
        $this->assertSame('2026-11-11 20:00', RemittanceDeadline::dueFor(Carbon::parse('2026-11-10 09:00'))->format('Y-m-d H:i'));
        config(['pickrush.policies.remittance_cycle' => 'weekly']);
        $this->assertSame('2026-11-16 20:00', RemittanceDeadline::dueFor(Carbon::parse('2026-11-10 09:00'))->format('Y-m-d H:i'));
    }

    private function slip(): UploadedFile
    {
        return UploadedFile::fake()->create('slip.pdf', 120, 'application/pdf');
    }

    public function test_large_cash_counts_need_a_second_person(): void
    {
        $rider = $this->rider($this->sanJose, ['cod_limit' => 50000]);
        $this->as($this->sanJoseManager);
        $parcel = $this->bringToSanJose($this->parcel(['cod_amount' => 25000]));
        $task = app(DispatchService::class)->assign($parcel, $rider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->deliver($task->fresh(), 25000, 'proof.jpg');

        $this->expectRefusal(fn () => app(CashHandling::class)->recordRiderRemittance($rider, null, $this->sanJoseManager), 'second staff member');
        $colleague = $this->staff(StaffRole::Dispatcher, $this->sanJose);
        $this->assertSame(CodRemittance::AWAITING_RIDER, app(CashHandling::class)->recordRiderRemittance($rider, null, $this->sanJoseManager, $colleague)->status);
    }

    // Returns ---------------------------------------------------------------------------------

    public function test_a_parcel_that_cannot_be_delivered_goes_back_to_the_seller(): void
    {
        $sanJoseRider = $this->rider($this->sanJose);
        $tanauanRider = $this->rider($this->tanauan);
        $parcel = $this->bringToSanJose($this->parcel());
        $this->as($this->sanJoseManager);
        $task = app(DispatchService::class)->assign($parcel, $sanJoseRider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->fail($task->fresh(), 'buyer_refused', null, 'refused.jpg');

        $this->as($this->sanJoseManager)->post($this->pickrushUrl("/station/failed/{$parcel->tracking_no}/return"), ['reason' => 'Buyer refused the parcel'])
            ->assertSessionHasNoErrors();
        $parcel->refresh();
        $this->assertTrue($parcel->is_return);
        $this->assertSame([ParcelStatus::ReturningToSender, $this->sanJose->parent_hub_id], [$parcel->current_status, $parcel->next_hub_id]);

        // Back up the chain: San Jose → Batangas → Tanauan.
        $manifests = app(ManifestService::class);
        $up = $manifests->create($this->sanJose, $this->sanJose->parent, $this->sanJoseManager);
        $manifests->add($up, $parcel->tracking_no);
        $this->dispatchTrip($up, $this->sanJoseManager);
        $sorter = $this->staff(StaffRole::Sorter, $this->tanauan->parent);
        $this->as($sorter);
        $manifests->receive($up, $parcel->tracking_no, $sorter);
        $this->assertSame($this->tanauan->id, $parcel->fresh()->next_hub_id, 'the return heads for the origin station');
        $down = $manifests->create($this->tanauan->parent, $this->tanauan, $sorter);
        $manifests->add($down, $parcel->tracking_no);
        $this->dispatchTrip($down, $sorter);
        $this->as($this->manager)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])->assertSessionHasNoErrors();

        $this->as($this->manager);
        $back = app(DispatchService::class)->assign($parcel->fresh(), $tanauanRider, $this->manager);
        $this->assertSame('return', $back->type);
        app(RiderTaskService::class)->startDelivery($back);
        app(RiderTaskService::class)->deliver($back->fresh(), 0, 'seller-signed.jpg');

        $this->assertSame(ParcelStatus::ReturnedToSender, $parcel->fresh()->current_status);
        $this->assertSame(0.0, app(CashHandling::class)->expectedFrom($tanauanRider), 'no cash on a return');
    }

    // Riders ----------------------------------------------------------------------------------

    public function test_hub_manager_approves_an_application_once_the_documents_are_in(): void
    {
        Storage::fake('local');
        $rider = $this->rider($this->tanauan, ['status' => 'pending', 'shift_status' => 'off_duty', 'created_by' => null, 'applied_at' => now()]);

        $this->as($this->dispatcher)->post($this->pickrushUrl("/station/riders/{$rider->id}/approve"))->assertForbidden();
        $this->as($this->manager)->post($this->pickrushUrl("/station/riders/{$rider->id}/approve"))->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'Missing documents'));
        // The rider uploads these in the app; the station only views them.
        foreach (['drivers_license', 'or_cr'] as $type) {
            $rider->documents()->create(['doc_type' => $type, 'expiry_date' => now()->addYear()->toDateString(),
                'file_path' => UploadedFile::fake()->create("{$type}.jpg", 50, 'image/jpeg')->store("pickrush/riders/{$rider->id}", 'local')]);
        }
        $this->as($this->dispatcher)->get($this->pickrushUrl('/station'))->assertOk()->assertSee('1 rider application to review');
        $this->as($this->manager)->get($this->pickrushUrl("/station/riders/{$rider->id}"))->assertOk()
            ->assertDontSee('NBI clearance')->assertDontSee('type="file"', false);

        $this->as($this->manager)->post($this->pickrushUrl("/station/riders/{$rider->id}/approve"))->assertSessionHasNoErrors();
        $this->assertSame(['active', $this->manager->id], [$rider->fresh()->status, $rider->fresh()->approved_by]);
        $this->as($this->manager)->post($this->pickrushUrl("/station/riders/{$rider->id}/approve"))->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'Only a pending application'));

        // Documents only open through a signed, short-lived link.
        $doc = $rider->documents()->first();
        $this->as($this->manager)->get($this->pickrushUrl("/station/riders/{$rider->id}/documents/{$doc->id}"))->assertForbidden();
        $page = $this->as($this->manager)->get($this->pickrushUrl("/station/riders/{$rider->id}"))->assertOk()->getContent();
        preg_match('#href="(/station/riders/\d+/documents/'.$doc->id.'\?[^"]+)"#', $page, $link);
        $this->assertNotEmpty($link, 'the page links the document by a signed path');

        // The tunnel hands requests to Picksell over plain http; the link still opens there.
        $this->as($this->manager)->get('http://'.config('pickrush.domain').html_entity_decode($link[1]))->assertOk();
    }

    public function test_a_rejected_application_keeps_the_reason_and_cannot_get_parcels(): void
    {
        $rider = $this->rider($this->tanauan, ['status' => 'pending', 'shift_status' => 'off_duty', 'created_by' => null]);

        $this->as($this->manager)->post($this->pickrushUrl("/station/riders/{$rider->id}/status"), ['action' => 'reject'])->assertSessionHasErrors('reason');
        $this->as($this->manager)->post($this->pickrushUrl("/station/riders/{$rider->id}/status"), ['action' => 'reject', 'reason' => 'License photo is blurry'])->assertSessionHasNoErrors();
        $this->assertSame(['rejected', 'License photo is blurry'], [$rider->fresh()->status, $rider->fresh()->status_reason]);

        $this->expectRefusal(fn () => app(DispatchService::class)->assign($this->parcel(), $rider->fresh(), $this->manager), 'not active');
    }

    public function test_rider_details_are_fixed_and_the_station_only_sets_the_home_zone(): void
    {
        $rider = $this->rider($this->tanauan, ['plate_no' => 'ABC 123', 'payout_method' => 'gcash', 'payout_account' => '09170000000']);
        $zone = Zone::withoutGlobalScopes()->create(['station_hub_id' => $this->tanauan->id, 'name' => 'Poblacion']);

        $this->as($this->manager)->get($this->pickrushUrl("/station/riders/{$rider->id}"))->assertOk()
            ->assertSee('ABC 123')->assertDontSee('name="plate_no"', false)->assertDontSee('name="mobile_no"', false)->assertSee('name="zone_id"', false);

        $this->as($this->manager)->put($this->pickrushUrl("/station/riders/{$rider->id}"), [
            'zone_id' => $zone->id, 'first_name' => 'Changed', 'mobile_no' => '09999999999', 'plate_no' => 'XYZ 999', 'payout_account' => 'someone else',
        ])->assertSessionHas('success', 'Home zone saved.');

        $before = $rider->only('full_name', 'mobile_no', 'plate_no', 'payout_account');
        $rider->refresh();
        $this->assertSame($zone->id, $rider->zone_id);
        $this->assertSame($before, $rider->only('full_name', 'mobile_no', 'plate_no', 'payout_account'));
    }

    public function test_stations_only_see_their_own_riders_and_hq_cannot_use_the_station_portal(): void
    {
        $theirs = $this->rider($this->sanJose);

        $this->as($this->manager)->get($this->pickrushUrl("/station/riders/{$theirs->id}"))->assertNotFound();
        $this->as($this->staff(StaffRole::SuperAdmin))->get($this->pickrushUrl('/station'))->assertForbidden();
        $this->as($this->staff(StaffRole::Sorter, $this->tanauan->parent))->get($this->pickrushUrl('/station/dispatch'))->assertForbidden();
        $this->as($this->dispatcher)->get($this->pickrushUrl('/'))->assertRedirect(route('pickrush.station.dashboard'));
    }

    // Zones -----------------------------------------------------------------------------------

    public function test_zones_hold_only_this_stations_barangays_and_a_barangay_moves_between_zones(): void
    {
        $this->as($this->manager)->post($this->pickrushUrl('/station/zones'), ['name' => 'Zone A', 'barangays' => ['041029001', '041029002']])->assertRedirect();
        $this->as($this->manager)->post($this->pickrushUrl('/station/zones'), ['name' => 'Zone B', 'barangays' => ['041005001']])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'Only barangays of City of Tanauan'));
        $this->as($this->manager)->post($this->pickrushUrl('/station/zones'), ['name' => 'Zone B', 'barangays' => ['041029002', '041029003']])->assertRedirect();

        $a = Zone::where('name', 'Zone A')->sole();
        $b = Zone::where('name', 'Zone B')->sole();
        $this->assertSame(['041029001'], $a->barangayLinks()->pluck('barangay_code')->all());
        $this->assertEqualsCanonicalizing(['041029002', '041029003'], $b->barangayLinks()->pluck('barangay_code')->all());
        $this->tanauan->update(['lat' => 14.0863, 'lng' => 121.1497]);
        $this->fleet($this->tanauan);
        $this->assertSame(['No approved rider'], $this->tanauan->fresh()->readinessGaps(), 'manager, truck, driver, zones and map pin are in place; only a rider is missing');
    }

    // Pages -----------------------------------------------------------------------------------

    public function test_every_station_page_renders(): void
    {
        $rider = $this->rider($this->sanJose);
        $applicant = $this->rider($this->sanJose, ['status' => 'pending', 'created_by' => null, 'first_name' => 'Ana', 'last_name' => 'Reyes', 'applied_at' => now()]);
        $parcel = $this->bringToSanJose($this->parcel());
        $trip = Manifest::where('to_hub_id', $this->sanJose->id)->sole();

        foreach (['/station', '/station/receiving', '/station/dispatch', '/station/dispatch?view=pickups', '/station/dispatch?view=assigned',
            '/station/failed', '/station/trips', "/station/trips/{$trip->manifest_no}", '/station/riders', "/station/riders/{$rider->id}",
            '/station/riders?status=pending', "/station/riders/{$applicant->id}", '/station/zones', '/station/zones/create', '/station/cash', '/parcels', "/parcels/{$parcel->tracking_no}"] as $page) {
            $this->as($this->sanJoseManager)->get($this->pickrushUrl($page))->assertOk();
        }
    }

    private function expectRefusal(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail("Expected the operation to be refused with: {$message}");
        } catch (OperationRefused $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
