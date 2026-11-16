<?php

namespace App\Pickrush\Http\Controllers\Province;

use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Route;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\Labels;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\RouteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Multi-stop routes: one truck from this province hub to several of its stations. Each stop's
 * parcels are loaded on its own trip page; the route sets the order and dispatches them together.
 */
class RouteController extends PortalController
{
    public function store(Request $request, RouteService $routes)
    {
        $data = $request->validate(['stations' => 'required|array|min:1', 'stations.*' => 'integer']);
        $hub = $this->hub();
        $stations = $hub->childStations()->whereIn('id', $data['stations'])->get();
        if ($stations->count() !== count(array_unique($data['stations']))) {
            return back()->withErrors(['operation' => 'Routes stop at this province\'s own stations only.'])->withInput();
        }

        try {
            $route = DB::transaction(function () use ($routes, $hub, $stations) {
                $route = $routes->create($hub, $this->staff());
                foreach ($stations as $station) {
                    $routes->addStop($route, $station, $this->staff());
                }

                return $route;
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pickrush.province.routes.show', $route)
            ->with('success', "Route {$route->route_no} started with {$stations->count()} stops. Load each stop, then dispatch.");
    }

    public function show(Route $tripRoute, ManifestService $manifests)
    {
        $route = $tripRoute->load('hub', 'stops.toHub');
        $hub = $this->hub();
        abort_unless($route->hub_id === $hub->id, 403, 'Only the sending hub manages a route.');

        return view('pickrush.province.route', [
            'route' => $route,
            'legs' => RouteService::legs($route),
            'ready' => $route->status === 'draft'
                ? $route->stops->mapWithKeys(fn ($s) => [$s->id => $manifests->readyFor($hub, $s->toHub)->count()])
                : collect(),
            'addable' => $hub->childStations()->whereNotIn('id', $route->stops->pluck('to_hub_id'))->orderBy('name')->get(),
            'maxStops' => RouteService::maxStops(),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('plate_no')->get(),
            'drivers' => Driver::where('is_active', true)->orderBy('full_name')->get(),
            'onTrip' => Manifest::fleetOnTheRoad(),
        ]);
    }

    /** The driver's route sheet: stops in order, each with its trip barcode. */
    public function print(Route $tripRoute, Labels $labels)
    {
        $this->ownOnly($tripRoute);

        return $labels->route($tripRoute);
    }

    public function addStop(Request $request, Route $tripRoute, RouteService $routes)
    {
        $this->ownOnly($tripRoute);
        $data = $request->validate(['to_hub_id' => 'required|integer']);
        $station = Hub::find($data['to_hub_id']) ?? abort(404);

        return $this->attempt(fn () => $routes->addStop($tripRoute, $station, $this->staff()), "{$station->name} added. Stops re-ordered nearest-next.");
    }

    public function removeStop(Route $tripRoute, Manifest $stop, RouteService $routes)
    {
        $this->ownOnly($tripRoute);

        return $this->attempt(fn () => $routes->removeStop($tripRoute, $stop), "{$stop->toHub->name} taken off the route. Its parcels went back to ready to leave.");
    }

    public function move(Request $request, Route $tripRoute, Manifest $stop, RouteService $routes)
    {
        $this->ownOnly($tripRoute);
        $data = $request->validate(['direction' => 'required|in:up,down']);

        return $this->attempt(fn () => $routes->move($tripRoute, $stop, $data['direction'] === 'up' ? -1 : 1), 'Stop order updated.');
    }

    public function autoOrder(Route $tripRoute, RouteService $routes)
    {
        $this->ownOnly($tripRoute);
        abort_unless($tripRoute->status === 'draft', 422);

        return $this->attempt(fn () => $routes->autoOrder($tripRoute), 'Stops re-ordered nearest-next.');
    }

    /** Load every ready parcel onto every stop in one go. */
    public function loadAll(Route $tripRoute, ManifestService $manifests)
    {
        $this->ownOnly($tripRoute);

        return $this->attempt(fn () => $tripRoute->stops->sum(fn ($stop) => $manifests->addAllReady($stop)), fn (int $n) => "{$n} parcels loaded across the stops.");
    }

    public function dispatch(Request $request, Route $tripRoute, RouteService $routes)
    {
        $this->ownOnly($tripRoute);
        $data = $request->validate(['vehicle_id' => 'required|integer', 'driver_id' => 'required|integer']);
        $vehicle = Vehicle::find($data['vehicle_id']);   // hub-scoped: another hub's truck is not found
        $driver = Driver::find($data['driver_id']);
        if (! $vehicle || ! $driver) {
            return back()->withErrors(['operation' => 'Pick one of this hub\'s registered trucks and drivers.'])->withInput();
        }

        return $this->attempt(fn () => $routes->dispatch($tripRoute, $vehicle, $driver, $this->staff()),
            "Route {$tripRoute->route_no} dispatched with {$vehicle->plate_no}.");
    }

    public function cancel(Route $tripRoute, RouteService $routes)
    {
        $this->ownOnly($tripRoute);
        try {
            $routes->cancel($tripRoute, $this->staff());
        } catch (\DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()]);
        }

        return redirect()->route('pickrush.province.trips.index')->with('success', "Route {$tripRoute->route_no} cancelled. Its parcels went back to ready to leave.");
    }

    private function ownOnly(Route $route): void
    {
        abort_unless($route->hub_id === $this->hub()->id, 403, 'Only the sending hub manages a route.');
    }
}
