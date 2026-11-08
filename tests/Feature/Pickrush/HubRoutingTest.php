<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubLink;
use App\Pickrush\Services\HubLinkBuilder;
use App\Pickrush\Services\HubRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Province-to-province relay along links between neighboring province hubs. */
class HubRoutingTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $batangas;

    private Hub $laguna;

    private Hub $southern;

    private Hub $eastern;

    private Hub $qc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->batangas = $this->station('041029000')->parent;
        $this->laguna = $this->station('043405000')->parent;
        $this->southern = $this->station('137603000')->parent;
        $this->qc = $this->station('137404000');
        $this->eastern = $this->qc->parent;
    }

    private function router(): HubRouter
    {
        return new HubRouter;   // reads links fresh
    }

    private function names(?array $path): ?array
    {
        return $path ? array_map(fn (Hub $hub) => $hub->name, $path) : null;
    }

    public function test_the_starting_links_connect_every_province_of_the_country(): void
    {
        $links = require database_path('data/pickrush-province-links.php');
        $pairs = [];
        $graph = [];
        foreach ($links as [$one, $other, $mode, $hours]) {
            $this->assertMatchesRegularExpression('/^\d{9}-\d{9}$/', "{$one}-{$other}");
            $this->assertNotSame($one, $other);
            $this->assertContains($mode, ['land', 'sea']);
            $this->assertGreaterThan(0, $hours);
            $key = min($one, $other).'-'.max($one, $other);
            $this->assertArrayNotHasKey($key, $pairs, "{$key} is listed twice");
            $pairs[$key] = true;
            $graph[$one][] = $other;
            $graph[$other][] = $one;
        }

        // 81 provinces and Metro Manila's 4 districts, all in one network.
        $this->assertCount(85, $graph);
        $seen = [array_key_first($graph) => true];
        $stack = [array_key_first($graph)];
        while ($stack) {
            foreach ($graph[array_pop($stack)] as $next) {
                if (! isset($seen[$next])) {
                    $seen[$next] = $stack[] = $next;
                }
            }
        }
        $this->assertCount(85, $seen, 'every province hub can reach every other one');
    }

    public function test_parcels_relay_along_the_fastest_chain_and_route_around_closed_links_and_hubs(): void
    {
        $this->assertSame(3, HubLink::count());   // Batangas–Laguna, Laguna–South, South–East: the real map
        $this->assertSame(0, app(HubLinkBuilder::class)->build(), 're-running adds nothing');
        $this->assertSame(
            ['Batangas Province Hub', 'Laguna Province Hub', 'Metro Manila Southern District Hub', 'Metro Manila Eastern District Hub', 'Quezon City Station'],
            $this->names($this->router()->path($this->batangas, $this->qc)));
        $this->assertTrue($this->laguna->is($this->router()->nextHop($this->batangas, $this->qc)));
        $this->assertTrue($this->router()->linked($this->batangas, $this->laguna));
        $this->assertFalse($this->router()->linked($this->batangas, $this->eastern));

        // A faster direct link wins; a slower one is ignored.
        [$a, $b] = HubLink::pair($this->batangas->id, $this->eastern->id);
        $direct = HubLink::create(['hub_a_id' => $a, 'hub_b_id' => $b, 'mode' => 'land', 'travel_hours' => 9]);
        $this->assertTrue($this->laguna->is($this->router()->nextHop($this->batangas, $this->qc)), '2+2+1 hours beats 9');
        $direct->update(['travel_hours' => 4]);
        $this->assertTrue($this->eastern->is($this->router()->nextHop($this->batangas, $this->qc)));

        // The direct road closes, then a hub on the way: routed around, or no route at all.
        $direct->update(['is_active' => false]);
        $this->assertTrue($this->laguna->is($this->router()->nextHop($this->batangas, $this->qc)));
        $this->southern->update(['is_active' => false]);
        $this->assertNull($this->router()->nextHop($this->batangas, $this->qc));
        $this->assertNull($this->router()->path($this->batangas, $this->qc));
        $this->assertEqualsCanonicalizing([$this->eastern->id], $this->router()->disconnected());

        // A hub that just closed still sends on what it holds.
        $this->southern->update(['is_active' => true]);
        $this->laguna->update(['is_active' => false]);
        $this->assertNull($this->router()->nextHop($this->batangas, $this->qc));
        $this->assertTrue($this->southern->is($this->router()->nextHop($this->laguna, $this->qc)));
    }

    public function test_hq_sees_the_links_and_only_a_super_admin_changes_them(): void
    {
        $finance = $this->staff(StaffRole::Finance);
        $admin = $this->staff(StaffRole::SuperAdmin);
        $link = HubLink::between($this->laguna, $this->southern);

        $this->actingAs($finance, 'pickrush')->get($this->pickrushUrl('/province-links'))->assertOk()
            ->assertSee('Laguna Province Hub ↔ Metro Manila Southern District Hub')->assertDontSee('Add a link');
        $this->actingAs($finance, 'pickrush')->put($this->pickrushUrl("/province-links/{$link->id}"), ['mode' => 'land', 'travel_hours' => 3])->assertForbidden();
        $this->actingAs($this->staff(StaffRole::Sorter, $this->batangas), 'pickrush')->get($this->pickrushUrl('/province-links'))->assertForbidden();

        // Turning the only road into Metro Manila off cuts it off, and the page says so.
        $this->actingAs($admin, 'pickrush')->put($this->pickrushUrl("/province-links/{$link->id}"), ['mode' => 'land', 'travel_hours' => 3])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'turned off'));
        $this->assertSame([false, 3.0], [$link->fresh()->is_active, $link->fresh()->travel_hours]);
        $this->actingAs($admin, 'pickrush')->get($this->pickrushUrl('/province-links'))->assertOk()
            ->assertSee('No route to 2 hubs')->assertSee('Add a link');

        $this->actingAs($admin, 'pickrush')->post($this->pickrushUrl('/province-links'), ['hub_one' => $this->eastern->id, 'hub_other' => $this->batangas->id, 'mode' => 'sea', 'travel_hours' => 6])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->eastern->is($this->router()->nextHop($this->batangas, $this->qc)));
        $this->actingAs($admin, 'pickrush')->post($this->pickrushUrl('/province-links'), ['hub_one' => $this->batangas->id, 'hub_other' => $this->eastern->id, 'mode' => 'land', 'travel_hours' => 5])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'already linked'));
        $this->actingAs($admin, 'pickrush')->post($this->pickrushUrl('/province-links'), ['hub_one' => $this->batangas->id, 'hub_other' => $this->qc->id, 'mode' => 'land', 'travel_hours' => 5])
            ->assertSessionHasErrors('hub_other');   // a station is not a province hub
    }
}
