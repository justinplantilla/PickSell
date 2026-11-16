<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Route;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\RouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $batangas;

    private Hub $tanauan;

    private Hub $sanJose;

    private Staff $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->sanJose = $this->station('041005000');
        $this->batangas = $this->tanauan->parent;
        $this->manager = $this->staff(StaffRole::HubManager, $this->batangas);
        $this->actingAs($this->manager, 'pickrush');
        // San Jose sits closer to the province hub than Tanauan.
        $this->batangas->update(['lat' => 13.756, 'lng' => 121.058]);
        $this->sanJose->update(['lat' => 13.877, 'lng' => 121.105]);
        $this->tanauan->update(['lat' => 14.086, 'lng' => 121.150]);
    }

    /** A parcel sitting at the province hub, due next at $station. */
    private function parcelFor(Hub $station): Parcel
    {
        $parcel = app(ParcelRegistrar::class)->register([
            'shipper_name' => 'Tote House', 'pickup_municipality_code' => '043405000', 'pickup_barangay_code' => '043405001',
            'consignee_name' => 'Ana Reyes', 'delivery_municipality_code' => $station->psgc_code, 'cod_amount' => 500,
        ]);
        $parcel->forceFill(['current_status' => ParcelStatus::AtProvinceHub, 'current_hub_id' => $this->batangas->id, 'next_hub_id' => $station->id])->save();

        return $parcel->fresh();
    }

    /** @return array{0: Route, 1: Manifest, 2: Manifest} route, Tanauan stop, San Jose stop */
    private function loadedRoute(): array
    {
        $routes = app(RouteService::class);
        $route = $routes->create($this->batangas, $this->manager);
        $tanauanStop = $routes->addStop($route, $this->tanauan, $this->manager);
        $sanJoseStop = $routes->addStop($route, $this->sanJose, $this->manager);
        $this->parcelFor($this->tanauan);
        $this->parcelFor($this->sanJose);
        app(ManifestService::class)->addAllReady($tanauanStop);
        app(ManifestService::class)->addAllReady($sanJoseStop);

        return [$route, $tanauanStop, $sanJoseStop];
    }

    public function test_stops_are_ordered_nearest_next_and_can_be_moved(): void
    {
        [$route, $tanauanStop, $sanJoseStop] = $this->loadedRoute();
        $this->assertSame([$sanJoseStop->id, $tanauanStop->id], $route->stops()->pluck('id')->all());

        app(RouteService::class)->move($route, $tanauanStop, -1);
        $this->assertSame([$tanauanStop->id, $sanJoseStop->id], $route->stops()->pluck('id')->all());
    }

    public function test_one_truck_dispatches_every_stop_and_is_free_after_the_last_arrives(): void
    {
        [$route, $tanauanStop, $sanJoseStop] = $this->loadedRoute();
        [$truck, $driver] = $this->fleet($this->batangas);
        app(RouteService::class)->dispatch($route, $truck, $driver, $this->manager);

        $this->assertSame('dispatched', $route->fresh()->status);
        foreach ([$tanauanStop, $sanJoseStop] as $stop) {
            $this->assertSame([$truck->id, $driver->id, 'dispatched'], [$stop->fresh()->vehicle_id, $stop->fresh()->driver_id, $stop->fresh()->status]);
        }
        // San Jose is first, Tanauan one leg later.
        $this->assertEqualsWithDelta(6 * 3600, $sanJoseStop->fresh()->due_at->diffInSeconds($tanauanStop->fresh()->due_at), 5);

        $manifests = app(ManifestService::class);
        $sanJoseStaff = $this->staff(StaffRole::HubManager, $this->sanJose);
        $manifests->truckArrived($sanJoseStop->fresh(), $sanJoseStaff);
        $this->assertSame('dispatched', $route->fresh()->status);   // still on the road to Tanauan
        $this->assertTrue(Manifest::fleetOnTheRoad()->contains('vehicle_id', $truck->id));

        $tanauanStaff = $this->staff(StaffRole::HubManager, $this->tanauan);
        $manifests->receive($tanauanStop->fresh(), $tanauanStop->items()->first()->parcel->tracking_no, $tanauanStaff);
        $this->assertSame('completed', $route->fresh()->status);
        $this->assertFalse(Manifest::fleetOnTheRoad()->contains('vehicle_id', $truck->id));
    }

    public function test_a_stop_cannot_leave_on_its_own_or_empty(): void
    {
        [$route, $tanauanStop] = $this->loadedRoute();
        [$truck, $driver] = $this->fleet($this->batangas);

        $this->assertRefused(fn () => app(ManifestService::class)->dispatch($tanauanStop, $truck, $driver, $this->manager), 'stop on a route');
        $this->assertRefused(fn () => app(ManifestService::class)->cancel($tanauanStop, $this->manager), 'stop on a route');

        $tanauanStop->items()->delete();
        $this->assertRefused(fn () => app(RouteService::class)->dispatch($route, $truck, $driver, $this->manager), 'has no parcels');
    }

    public function test_stops_are_own_stations_without_repeats_up_to_the_limit(): void
    {
        config(['pickrush.fleet.route_max_stops' => 1]);
        $routes = app(RouteService::class);
        $route = $routes->create($this->batangas, $this->manager);
        $routes->addStop($route, $this->tanauan, $this->manager);

        $this->assertRefused(fn () => $routes->addStop($route, $this->tanauan, $this->manager), 'already a stop');
        $this->assertRefused(fn () => $routes->addStop($route, $this->sanJose, $this->manager), 'at most 1 stops');
        $this->assertRefused(fn () => $routes->addStop($route, $this->station('043405000'), $this->manager), 'not one of');
        $this->assertRefused(fn () => $routes->create($this->tanauan, $this->manager), 'province hub');
    }

    public function test_removing_a_stop_or_cancelling_frees_its_parcels(): void
    {
        [$route, $tanauanStop, $sanJoseStop] = $this->loadedRoute();
        $routes = app(RouteService::class);
        $routes->removeStop($route, $sanJoseStop);
        $this->assertSame([[$tanauanStop->id, 1]], $route->stops()->get()->map(fn ($s) => [$s->id, $s->stop_order])->all());
        $this->assertSame(1, app(ManifestService::class)->readyFor($this->batangas, $this->sanJose)->count());

        $routes->cancel($route, $this->manager);
        $this->assertNull(Route::find($route->id));
        $this->assertSame(0, Manifest::where('route_id', $route->id)->count());
    }

    public function test_the_province_portal_starts_loads_orders_and_dispatches_a_route(): void
    {
        $this->parcelFor($this->tanauan);
        $this->parcelFor($this->sanJose);
        [$truck, $driver] = $this->fleet($this->batangas);

        $this->get($this->pickrushUrl('/province/trips'))->assertOk()->assertSee('Multi-stop routes')->assertSee('Start route');
        $this->post($this->pickrushUrl('/province/routes'), ['stations' => [$this->station('043405000')->id]])
            ->assertSessionHasErrors('operation');   // Calamba is Laguna's
        $this->post($this->pickrushUrl('/province/routes'), ['stations' => [$this->tanauan->id, $this->sanJose->id]])->assertRedirect();
        $route = Route::sole();
        [$first, $second] = $route->stops()->get()->all();
        $this->assertSame($this->sanJose->id, $first->to_hub_id);

        $this->get($this->pickrushUrl("/province/routes/{$route->route_no}"))->assertOk()
            ->assertSee('Load all 2 ready parcels')->assertSee($truck->plate_no)->assertSeeInOrder([$this->sanJose->name, $this->tanauan->name]);
        // A stop's own trip page loads parcels but sends you back to the route to dispatch.
        $this->get($this->pickrushUrl("/province/trips/{$first->manifest_no}"))->assertOk()
            ->assertSee("Stop 1 on route")->assertSee($route->route_no)->assertSee('This stop leaves with its route.')->assertDontSee('Cancel trip');
        $this->get($this->pickrushUrl('/province/trips'))->assertDontSee('Continue loading');   // stops are not single drafts

        $this->post($this->pickrushUrl("/province/routes/{$route->route_no}/stops/{$second->manifest_no}/move"), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame($this->tanauan->id, $route->stops()->first()->to_hub_id);
        $this->post($this->pickrushUrl("/province/routes/{$route->route_no}/load-all"))->assertSessionHas('success', '2 parcels loaded across the stops.');
        $this->post($this->pickrushUrl("/province/routes/{$route->route_no}/dispatch"), ['vehicle_id' => $truck->id, 'driver_id' => $driver->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('dispatched', $route->fresh()->status);
        $this->get($this->pickrushUrl("/province/routes/{$route->route_no}"))->assertOk()->assertSee('On the road')->assertSee('On the way');
        $this->get($this->pickrushUrl('/fleet'))->assertOk()->assertSee('On a trip');
    }

        public function test_everyone_sees_where_the_truck_is_and_the_driver_gets_a_route_sheet(): void
    {
        [$route, $tanauanStop, $sanJoseStop] = $this->loadedRoute();
        [$truck, $driver] = $this->fleet($this->batangas);
        app(RouteService::class)->dispatch($route, $truck, $driver, $this->manager);
        $this->assertSame('On the way to '.$this->sanJose->name.' (stop 1 of 2)', $route->fresh()->position());

        $this->get($this->pickrushUrl("/province/routes/{$route->route_no}/print"))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get($this->pickrushUrl("/province/trips/{$tanauanStop->manifest_no}/print"))->assertOk()->assertHeader('content-type', 'application/pdf');

        app(ManifestService::class)->truckArrived($sanJoseStop->fresh(), $this->staff(StaffRole::HubManager, $this->sanJose));
        $where = "Dropped at {$this->sanJose->name} (1 of 2), heading to {$this->tanauan->name}";
        $this->assertSame($where, $route->fresh()->position());

        $this->actingAs($this->staff(StaffRole::HubManager, $this->tanauan), 'pickrush');
        $this->get($this->pickrushUrl('/station/receiving'))->assertOk()->assertSee('Stop 2 of 2')->assertSee($where);
        $this->get($this->pickrushUrl("/station/trips/{$tanauanStop->manifest_no}"))->assertOk()->assertSee($where);

        $this->actingAs($this->staff(StaffRole::SuperAdmin), 'pickrush');
        $this->get($this->pickrushUrl('/hq'))->assertOk()->assertSee('Multi-stop routes on the road (1)')->assertSee($route->route_no)->assertSee($where);
    }

        private function assertRefused(callable $fn, string $message): void
    {
        try {
            $fn();
            $this->fail("Expected refusal: {$message}");
        } catch (OperationRefused $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
