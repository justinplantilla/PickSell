<?php

namespace App\Pickrush\Services;

use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubLink;
use SplPriorityQueue;

/**
 * Network routing. A parcel moves station → its province hub → (province hubs in between) →
 * destination province hub → destination station; a return runs the same way towards the origin
 * station. Between provinces it relays along links between neighboring province hubs (land or
 * ferry), taking the fastest chain by travel hours. The next hop is worked out again at every
 * hub, so a link or hub HQ turns off is routed around from then on.
 */
class HubRouter
{
    /** @var array<int, array<int, float>>|null province hub id => [neighbor id => hours], active links only */
    private ?array $graph = null;

    /** @var array<int, int> active province hub ids (as keys) */
    private array $activeHubs = [];

    /** The company's station for a city or municipality (Pickrush when no company is given). */
    public function stationFor(?string $municipalityCode, ?int $companyId = null): ?Hub
    {
        return $municipalityCode
            ? Hub::ofCompany($companyId ?? Company::defaultId())->stations()->where('psgc_code', $municipalityCode)->first()
            : null;
    }

    /** The hub a parcel at $current must go to next to reach $target, or null when it is there or no route exists. */
    public function nextHop(Hub $current, Hub $target): ?Hub
    {
        if ($current->is($target)) {
            return null;
        }
        if ($current->isStation()) {
            return $current->parent;
        }
        $targetProvince = $target->provinceHub();
        if ($current->is($targetProvince)) {
            return $target;
        }
        $ids = $this->provincePath($current->id, $targetProvince->id);

        return $ids ? Hub::find($ids[1]) : null;
    }

    /**
     * Every hub from $from to $target, both included, or null when no route exists.
     *
     * @return list<Hub>|null
     */
    public function path(Hub $from, Hub $target): ?array
    {
        if ($from->is($target)) {
            return [$from];
        }
        $start = $from->provinceHub();
        $end = $target->provinceHub();
        $ids = $start->is($end) ? [$start->id] : $this->provincePath($start->id, $end->id);
        if (! $ids) {
            return null;
        }
        $hubs = Hub::whereIn('id', $ids)->get()->keyBy('id');
        $path = array_map(fn ($id) => $hubs[$id], $ids);
        if ($from->isStation()) {
            array_unshift($path, $from);
        }
        if ($target->isStation()) {
            $path[] = $target;
        }

        return $path;
    }

    /** Whether a trip may run from one province hub straight to the other: an active link to an active hub. */
    public function linked(Hub $from, Hub $to): bool
    {
        return isset($this->graph()[$from->id][$to->id], $this->activeHubs[$to->id]);
    }

    /** Province hubs a trip may run to from $hub. */
    public function neighbors(Hub $hub)
    {
        $ids = array_keys(array_intersect_key($this->graph()[$hub->id] ?? [], $this->activeHubs));

        return Hub::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Active province hubs cut off from the main network (the largest connected group): parcels
     * to or from them have no route until HQ adds or turns on a link.
     *
     * @return list<int>
     */
    public function disconnected(): array
    {
        $graph = $this->graph();
        $groups = [];
        $seen = [];
        foreach (array_keys($this->activeHubs) as $start) {
            if (isset($seen[$start])) {
                continue;
            }
            $group = [];
            $stack = [$start];
            $seen[$start] = true;
            while ($stack) {
                $hub = array_pop($stack);
                $group[] = $hub;
                foreach (array_keys($graph[$hub] ?? []) as $next) {
                    if (isset($this->activeHubs[$next]) && ! isset($seen[$next])) {
                        $seen[$next] = true;
                        $stack[] = $next;
                    }
                }
            }
            $groups[] = $group;
        }
        usort($groups, fn ($a, $b) => count($b) <=> count($a));

        return array_merge(...array_slice($groups, 1));
    }

    /**
     * Fastest chain of province hub ids by total travel hours (Dijkstra). Hubs on the way and at
     * the end must be active; the hub the parcel is at may be closing and still send it on.
     *
     * @return list<int>|null
     */
    private function provincePath(int $from, int $to): ?array
    {
        $graph = $this->graph();
        $hours = [$from => 0.0];
        $previous = [];
        $queue = new SplPriorityQueue;
        $queue->insert($from, 0);

        while (! $queue->isEmpty()) {
            $hub = $queue->extract();
            if ($hub === $to) {
                $path = [$to];
                while (isset($previous[$path[0]])) {
                    array_unshift($path, $previous[$path[0]]);
                }

                return $path;
            }
            foreach ($graph[$hub] ?? [] as $next => $legHours) {
                if (! isset($this->activeHubs[$next])) {
                    continue;
                }
                $total = $hours[$hub] + $legHours;
                if (! isset($hours[$next]) || $total < $hours[$next]) {
                    $hours[$next] = $total;
                    $previous[$next] = $hub;
                    $queue->insert($next, -$total);   // SplPriorityQueue serves the highest priority first
                }
            }
        }

        return null;
    }

    /** Active links and active province hubs, read once per router. */
    private function graph(): array
    {
        if ($this->graph !== null) {
            return $this->graph;
        }
        $this->activeHubs = Hub::provinces()->where('is_active', true)->pluck('id')->flip()->all();
        $this->graph = [];
        foreach (HubLink::where('is_active', true)->get(['hub_a_id', 'hub_b_id', 'travel_hours']) as $link) {
            $this->graph[$link->hub_a_id][$link->hub_b_id] = $link->travel_hours;
            $this->graph[$link->hub_b_id][$link->hub_a_id] = $link->travel_hours;
        }

        return $this->graph;
    }
}
