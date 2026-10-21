<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Zone;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\Station\CashHandling;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Services\Station\RiderTaskService;
use App\Pickrush\Services\Station\ZoneSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        return Rider::withoutGlobalScopes()->create(array_merge([
            'station_hub_id' => $station->id, 'full_name' => "Rider {$n}", 'mobile_no' => '0918'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle', 'status' => 'active', 'shift_status' => 'on_shift',
            'cod_limit' => 10000, 'max_parcels' => 30, 'created_by' => $this->manager->id,
        ], $overrides));
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
        $manifests->dispatch($out, 'ABC 123', 'Mang Jose', $this->dispatcher);

        $this->as($sorter);
        $manifests->receive($out, $parcel->tracking_no, $sorter);
        $down = $manifests->create($batangas, $this->sanJose, $sorter);
        $manifests->add($down, $parcel->tracking_no);
        $manifests->dispatch($down, 'XYZ 789', 'Mang Pedro', $sorter);

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
        $this->as($this->dispatcher)->post($this->pickrushUrl("/station/trips/{$trip->manifest_no}/dispatch"), ['vehicle_plate' => 'abc 123', 'driver_name' => 'Mang Jose'])
            ->assertSessionHasNoErrors();

        $this->assertSame([ParcelStatus::InTransitToProvince, $this->tanauan->parent_hub_id], [$arrives->fresh()->current_status, $arrives->fresh()->next_hub_id]);
        $this->assertSame(['dispatched', 'ABC 123'], [$trip->fresh()->status, $trip->fresh()->vehicle_plate]);

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

    public function test_cash_remittance_variance_blocks_dispatch_until_resolved_then_goes_to_hq(): void
    {
        $rider = $this->rider($this->sanJose);
        $this->as($this->sanJoseManager);
        $first = $this->bringToSanJose($this->parcel());
        $task = app(DispatchService::class)->assign($first, $rider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->deliver($task->fresh(), 1500, 'proof.jpg');

        $this->assertSame(1500.0, app(CashHandling::class)->expectedFrom($rider));
        $this->as($this->sanJoseManager)->post($this->pickrushUrl("/station/cash/riders/{$rider->id}"), ["received_amount" => 1400])->assertRedirect()->assertSessionHasNoErrors();
        $remittance = CodRemittance::sole();
        $this->assertSame(['disputed', '-100.00'], [$remittance->status, $remittance->variance]);
        $this->assertSame(0.0, app(CashHandling::class)->expectedFrom($rider), 'the cash is counted once');

        $next = $this->bringToSanJose($this->parcel());
        $this->expectRefusal(fn () => app(DispatchService::class)->assign($next, $rider, $this->sanJoseManager), 'unresolved cash variance');

        $this->as($this->staff(StaffRole::Dispatcher, $this->sanJose))->post($this->pickrushUrl("/station/cash/remittances/{$remittance->id}/resolve"), ['resolution' => 'Short ₱100, deducted from earnings'])
            ->assertForbidden();
        $this->as($this->sanJoseManager)->post($this->pickrushUrl("/station/cash/remittances/{$remittance->id}/resolve"), ['resolution' => 'Short ₱100, deducted from earnings'])
            ->assertSessionHasNoErrors();
        app(DispatchService::class)->assign($next->fresh(), $rider, $this->sanJoseManager);

        $this->as($this->sanJoseManager)->post($this->pickrushUrl('/station/cash/remit'), ['amount' => 1400, 'reference_no' => 'BDO-0001'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, app(CashHandling::class)->stationCashOnHand($this->sanJose));
        $this->assertNotNull(CodRemittance::withoutGlobalScopes()->sole()->hub_remittance_id, 'the rider cash is now part of the remittance to HQ');
    }

    public function test_large_cash_counts_need_a_second_person(): void
    {
        $rider = $this->rider($this->sanJose, ['cod_limit' => 50000]);
        $this->as($this->sanJoseManager);
        $parcel = $this->bringToSanJose($this->parcel(['cod_amount' => 25000]));
        $task = app(DispatchService::class)->assign($parcel, $rider, $this->sanJoseManager);
        app(RiderTaskService::class)->startDelivery($task);
        app(RiderTaskService::class)->deliver($task->fresh(), 25000, 'proof.jpg');

        $this->expectRefusal(fn () => app(CashHandling::class)->recordRiderRemittance($rider, 25000, $this->sanJoseManager), 'second staff member');
        $colleague = $this->staff(StaffRole::Dispatcher, $this->sanJose);
        $this->assertSame('reconciled', app(CashHandling::class)->recordRiderRemittance($rider, 25000, $this->sanJoseManager, $colleague)->status);
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
        $manifests->dispatch($up, 'RET 1', 'Mang Ben', $this->sanJoseManager);
        $sorter = $this->staff(StaffRole::Sorter, $this->tanauan->parent);
        $this->as($sorter);
        $manifests->receive($up, $parcel->tracking_no, $sorter);
        $this->assertSame($this->tanauan->id, $parcel->fresh()->next_hub_id, 'the return heads for the origin station');
        $down = $manifests->create($this->tanauan->parent, $this->tanauan, $sorter);
        $manifests->add($down, $parcel->tracking_no);
        $manifests->dispatch($down, 'RET 2', 'Mang Ben', $sorter);
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
        $this->assertSame(['No approved rider'], $this->tanauan->fresh()->readinessGaps(), 'manager and zones are in place; only a rider is missing');
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
