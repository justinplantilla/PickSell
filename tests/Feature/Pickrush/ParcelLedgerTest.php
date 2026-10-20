<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use App\Pickrush\Services\InvalidParcelTransition;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\ParcelStatusChanged;
use App\Pickrush\Services\TrackingNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use Tests\TestCase;

class ParcelLedgerTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private ParcelLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->ledger = app(ParcelLedger::class);
    }

    /** Tanauan (Batangas) to Quezon City (Metro Manila) unless overridden. */
    private function parcel(array $overrides = []): Parcel
    {
        return app(ParcelRegistrar::class)->register(array_merge([
            'shipper_name' => 'Tote House', 'pickup_address' => 'Altura Bata, Tanauan',
            'pickup_barangay_code' => '041029001', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Maria Santos', 'consignee_mobile' => '09171234567',
            'delivery_address' => '45A Bagong Pag-asa, Quezon City',
            'delivery_barangay_code' => '137404001', 'delivery_municipality_code' => '137404000',
            'payment_type' => 'cod', 'cod_amount' => 1200,
        ], $overrides));
    }

    public function test_registration_resolves_hubs_and_opens_the_log(): void
    {
        $parcel = $this->parcel();

        $this->assertTrue(TrackingNumber::isValid($parcel->tracking_no));
        $this->assertSame(ParcelStatus::WaitingPickup, $parcel->current_status);
        $this->assertSame('Tanauan City Station', $parcel->originHub->name);
        $this->assertSame('Quezon City Station', $parcel->destHub->name);
        $this->assertSame($parcel->origin_hub_id, $parcel->next_hub_id, 'the origin station collects it');
        $this->assertFalse($parcel->needs_address_fix);
        $this->assertSame(1, $parcel->events()->count());

        $unknown = $this->parcel(['delivery_municipality_code' => '999999999']);
        $this->assertTrue($unknown->needs_address_fix);
        $this->assertNull($unknown->dest_hub_id);
    }

    public function test_a_parcel_records_every_hub_it_passes_through(): void
    {
        $parcel = $this->parcel();
        $tanauan = $this->station('041029000');
        $batangas = $tanauan->parent;
        $qc = $this->station('137404000');
        $eastern = $qc->parent;

        $this->ledger->record($parcel, ParcelStatus::PickedUp, $tanauan);
        $this->ledger->record($parcel, ParcelStatus::AtOriginHub, $tanauan);
        $this->assertSame([$tanauan->id, $batangas->id], [$parcel->current_hub_id, $parcel->next_hub_id]);

        $this->ledger->record($parcel, ParcelStatus::InTransitToProvince, $tanauan, ['to_hub' => $batangas]);
        $this->assertSame([null, $batangas->id], [$parcel->current_hub_id, $parcel->next_hub_id]);

        $this->ledger->record($parcel, ParcelStatus::AtProvinceHub, $batangas);
        $this->assertSame($eastern->id, $parcel->next_hub_id, 'different province: hand over to the destination hub');

        $this->ledger->record($parcel, ParcelStatus::InTransitToProvince, $batangas, ['to_hub' => $eastern]);
        $this->ledger->record($parcel, ParcelStatus::AtProvinceHub, $eastern);
        $this->assertSame($qc->id, $parcel->next_hub_id);

        $this->ledger->record($parcel, ParcelStatus::InTransitToStation, $eastern, ['to_hub' => $qc]);
        $this->ledger->record($parcel, ParcelStatus::ArrivedAtStation, $qc);
        $this->assertNull($parcel->next_hub_id, 'at the destination station');

        $this->ledger->record($parcel, ParcelStatus::AssignedToRider, $qc);
        $this->ledger->record($parcel, ParcelStatus::OutForDelivery, $qc);
        $this->ledger->record($parcel, ParcelStatus::DeliveryFailed, $qc, ['note' => 'buyer_unavailable']);
        $this->assertSame(1, $parcel->attempt_count);
        $this->ledger->record($parcel, ParcelStatus::AssignedToRider, $qc);
        $this->ledger->record($parcel, ParcelStatus::OutForDelivery, $qc);
        $this->ledger->record($parcel, ParcelStatus::Delivered, $qc);

        $parcel->refresh();
        $this->assertSame(ParcelStatus::Delivered, $parcel->current_status);
        $this->assertNotNull($parcel->delivered_at);
        $this->assertSame([
            'Shipment created, waiting for pickup',
            'Picked up from the seller',
            'Arrived at Tanauan City Station',
            'Departed Tanauan City Station → Batangas Province Hub',
            'Arrived at Batangas Province Hub',
            'Departed Batangas Province Hub → Metro Manila Eastern District Hub',
            'Arrived at Metro Manila Eastern District Hub',
            'Departed Metro Manila Eastern District Hub → Quezon City Station',
            'Arrived at Quezon City Station',
            'Rider assigned for delivery',
            'Out for delivery',
            'Delivery attempt unsuccessful',
            'Rider assigned for delivery',
            'Out for delivery',
            'Delivered',
        ], $parcel->events->map->headline()->all());
    }

    public function test_same_province_goes_straight_from_province_hub_to_station(): void
    {
        $parcel = $this->parcel(['delivery_municipality_code' => '041005000']); // San Jose, Batangas
        $tanauan = $this->station('041029000');

        $this->ledger->record($parcel, ParcelStatus::AtOriginHub, $tanauan);
        $this->ledger->record($parcel, ParcelStatus::InTransitToProvince, $tanauan, ['to_hub' => $tanauan->parent]);
        $this->ledger->record($parcel, ParcelStatus::AtProvinceHub, $tanauan->parent);

        $this->assertSame($this->station('041005000')->id, $parcel->next_hub_id);
    }

    public function test_illegal_moves_are_refused_and_nothing_is_written(): void
    {
        $parcel = $this->parcel();

        try {
            $this->ledger->record($parcel, ParcelStatus::Delivered, $this->station('137404000'));
            $this->fail('A parcel waiting for pickup cannot be delivered.');
        } catch (InvalidParcelTransition $e) {
            $this->assertStringContainsString('cannot go from "Waiting for pickup" to "Delivered"', $e->getMessage());
        }
        $this->assertSame(1, $parcel->events()->count());
        $this->assertSame(ParcelStatus::WaitingPickup, $parcel->fresh()->current_status);
    }

    public function test_a_repeated_scan_with_the_same_key_is_recorded_once(): void
    {
        Event::fake([ParcelStatusChanged::class]);
        $parcel = $this->parcel();
        $tanauan = $this->station('041029000');

        $first = $this->ledger->record($parcel, ParcelStatus::PickedUp, $tanauan, ['idempotency_key' => 'scan-abc', 'occurred_at' => now()->subHour()]);
        $again = $this->ledger->record($parcel, ParcelStatus::PickedUp, $tanauan, ['idempotency_key' => 'scan-abc']);

        $this->assertTrue($first->is($again));
        $this->assertSame(2, $parcel->events()->count());
        $this->assertEquals(now()->subHour()->startOfSecond(), $first->occurred_at->startOfSecond(), 'offline scans keep their scan time');
        Event::assertDispatchedTimes(ParcelStatusChanged::class, 2); // waiting_pickup + picked_up
    }

    public function test_events_and_audit_rows_cannot_be_changed(): void
    {
        $event = $this->parcel()->events()->first();

        $this->expectException(LogicException::class);
        $event->update(['note' => 'rewritten']);
    }

    public function test_writes_are_audited_with_the_acting_staff_and_hub(): void
    {
        $tanauan = $this->station('041029000');
        $manager = $this->staff(StaffRole::HubManager, $tanauan);
        $this->actingAs($manager, 'pickrush');

        $tanauan->update(['address' => 'J.P. Laurel Hwy']);

        $log = AuditLog::where('action', 'Hub.updated')->sole();
        $this->assertSame(['staff', $manager->id, $tanauan->id], [$log->actor_type, $log->actor_id, $log->actor_hub_id]);
        $this->assertSame([['address' => null], ['address' => 'J.P. Laurel Hwy']], [$log->before, $log->after]);
        $this->assertArrayNotHasKey('password', AuditLog::where('action', 'Staff.created')->firstOrFail()->after);
    }

    public function test_hub_staff_only_see_parcels_of_their_own_hub(): void
    {
        $tanauanParcel = $this->parcel();
        $calambaParcel = $this->parcel(['pickup_municipality_code' => '043405000', 'delivery_municipality_code' => '043426000']);

        $this->actingAs($this->staff(StaffRole::Dispatcher, $this->station('041029000')), 'pickrush');
        $this->assertSame([$tanauanParcel->id], Parcel::pluck('id')->all());
        $this->assertNull(Parcel::find($calambaParcel->id), 'a guessed id from another hub is not found');

        $this->actingAs($this->staff(StaffRole::Dispatcher, $this->station('137404000')), 'pickrush');
        $this->assertSame([$tanauanParcel->id], Parcel::pluck('id')->all(), 'the destination station sees inbound parcels');

        $this->actingAs($this->staff(StaffRole::Finance), 'pickrush');
        $this->assertCount(2, Parcel::all(), 'HQ sees the whole network');
    }
}
