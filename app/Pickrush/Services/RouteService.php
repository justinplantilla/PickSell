<?php

namespace App\Pickrush\Services;

use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Route;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Multi-stop routes: one province hub truck drops at several of its own stations in one run.
 * Each stop is an ordinary trip (manifest) loaded and checked as usual; the route ties them to
 * one truck and driver and sets the order. Stops are ordered nearest-next from the province hub
 * by hub coordinates, and the manager can move them before dispatch. Each stop is due one local
 * leg later than the stop before it. The truck is free again once the last stop has arrived.
 */
class RouteService
{
    public function __construct(private ManifestService $manifests) {}

    public static function maxStops(): int
    {
        return (int) config('pickrush.fleet.route_max_stops', 5);
    }

    public function create(Hub $province, Staff $staff): Route
    {
        if (! $province->isProvince()) {
            throw new OperationRefused('Only a province hub sends multi-stop routes.');
        }

        return Route::create([
            'route_no' => 'RT'.now()->format('ymd').strtoupper(Str::random(6)),
            'hub_id' => $province->id,
            'status' => 'draft',
            'created_by' => $staff->id,
        ]);
    }

    /** Add a station as a stop: it gets its own trip, and the stops are re-ordered nearest-next. */
    public function addStop(Route $route, Hub $station, Staff $staff): Manifest
    {
        return DB::transaction(function () use ($route, $station, $staff) {
            $route = $this->lockDraft($route);
            if (! $station->isStation() || $station->parent_hub_id !== $route->hub_id) {
                throw new OperationRefused("{$station->name} is not one of {$route->hub->name}'s stations.");
            }
            if ($route->stops()->where('to_hub_id', $station->id)->exists()) {
                throw new OperationRefused("{$station->name} is already a stop on this route.");
            }
            if ($route->stops()->count() >= self::maxStops()) {
                throw new OperationRefused('A route has at most '.self::maxStops().' stops.');
            }
            $stop = $this->manifests->create($route->hub, $station, $staff);
            $stop->update(['route_id' => $route->id, 'stop_order' => $route->stops()->count() + 1]);
            $this->autoOrder($route);

            return $stop->fresh();
        });
    }

    /** Take a stop off: its trip goes, and its parcels go back to ready to leave. */
    public function removeStop(Route $route, Manifest $stop): void
    {
        DB::transaction(function () use ($route, $stop) {
            $route = $this->lockDraft($route);
            if ($stop->route_id !== $route->id) {
                throw new OperationRefused('That trip is not a stop on this route.');
            }
            $stop->delete();
            $this->renumber($route, $route->stops()->pluck('id')->all());
        });
    }

    /**
     * Order the stops nearest-next: from the province hub to the closest station, then the closest
     * to that, and so on. Stations without coordinates keep their place at the end.
     */
    public function autoOrder(Route $route): void
    {
        $stops = $route->stops()->with('toHub')->get();
        $located = $stops->filter(fn ($s) => $s->toHub->lat !== null && $s->toHub->lng !== null);
        $order = [];
        $here = $route->hub;
        if ($here->lat !== null && $here->lng !== null) {
            $left = $located->keyBy('id');
            while ($left->isNotEmpty()) {
                $next = $left->sortBy(fn ($s) => self::km($here, $s->toHub))->first();
                $order[] = $next->id;
                $here = $next->toHub;
                $left->forget($next->id);
            }
        }
        foreach ($stops as $stop) {
            if (! in_array($stop->id, $order, true)) {
                $order[] = $stop->id;
            }
        }
        $this->renumber($route, $order);
    }

    /** Move a stop one place earlier (-1) or later (+1). */
    public function move(Route $route, Manifest $stop, int $direction): void
    {
        DB::transaction(function () use ($route, $stop, $direction) {
            $route = $this->lockDraft($route);
            $ids = $route->stops()->pluck('id')->all();
            $at = array_search($stop->id, $ids, true);
            if ($at === false) {
                throw new OperationRefused('That trip is not a stop on this route.');
            }
            $to = $at + ($direction < 0 ? -1 : 1);
            if (! isset($ids[$to])) {
                return;
            }
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            $this->renumber($route, $ids);
        });
    }

    /** Cancel a route still being loaded: every stop's parcels go back to ready to leave. */
    public function cancel(Route $route, Staff $staff): void
    {
        DB::transaction(function () use ($route, $staff) {
            $route = $this->lockDraft($route);
            if ($route->hub_id !== $staff->hub_id) {
                throw new OperationRefused('Only the hub sending this route can cancel it.');
            }
            $route->delete();   // its stops' trips go with it
        });
    }

    /**
     * The whole route leaves with one truck and driver. Every stop needs a parcel; stop N is due
     * N local legs after dispatch.
     */
    public function dispatch(Route $route, Vehicle $vehicle, Driver $driver, Staff $staff): void
    {
        DB::transaction(function () use ($route, $vehicle, $driver, $staff) {
            $route = $this->lockDraft($route);
            $stops = $route->stops()->lockForUpdate()->get();
            if ($stops->isEmpty()) {
                throw new OperationRefused('Add at least one station before dispatching.');
            }
            if ($empty = $stops->first(fn ($s) => $s->items()->count() === 0)) {
                throw new OperationRefused("{$empty->toHub->name} has no parcels on it. Load it or remove the stop.");
            }
            $this->manifests->assertCanDrive($route->hub, $vehicle, $driver);

            $leg = (int) config('pickrush.fleet.trip_hours.local', 6);
            foreach ($stops->values() as $i => $stop) {
                $this->manifests->send($stop, $vehicle, $driver, $staff, $leg * ($i + 1));
            }
            $route->update([
                'status' => 'dispatched',
                'vehicle_id' => $vehicle->id, 'vehicle_plate' => $vehicle->plate_no,
                'driver_id' => $driver->id, 'driver_name' => $driver->full_name,
                'dispatched_by' => $staff->id, 'dispatched_at' => now(),
            ]);
        });
    }

    private function lockDraft(Route $route): Route
    {
        $route = Route::withoutGlobalScope('hub')->whereKey($route->id)->lockForUpdate()->firstOrFail();
        if ($route->status !== 'draft') {
            throw new OperationRefused('This route has left; start a new one.');
        }

        return $route;
    }

    /** @param  list<int>  $ids  stop trip ids in the new order */
    private function renumber(Route $route, array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            Manifest::withoutGlobalScope('hub')->whereKey($id)->where('route_id', $route->id)->update(['stop_order' => $i + 1]);
        }
    }

    /**
     * Straight-line km of each leg, keyed by stop trip id: from the province hub to stop 1, then
     * stop to stop. Null where a hub has no coordinates.
     *
     * @return array<int, ?float>
     */
    public static function legs(Route $route): array
    {
        $legs = [];
        $here = $route->hub;
        foreach ($route->stops as $stop) {
            $there = $stop->toHub;
            $legs[$stop->id] = ($here->lat !== null && $here->lng !== null && $there->lat !== null && $there->lng !== null)
                ? round(self::km($here, $there), 1) : null;
            $here = $there;
        }

        return $legs;
    }

    /** Straight-line distance in km between two hubs. */
    private static function km(Hub $a, Hub $b): float
    {
        $rad = M_PI / 180;
        $dLat = ($b->lat - $a->lat) * $rad;
        $dLng = ($b->lng - $a->lng) * $rad;
        $h = sin($dLat / 2) ** 2 + cos($a->lat * $rad) * cos($b->lat * $rad) * sin($dLng / 2) ** 2;

        return 12742 * asin(min(1, sqrt($h)));
    }
}
