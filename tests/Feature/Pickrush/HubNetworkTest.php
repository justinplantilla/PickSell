<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\HubNetworkBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubNetworkTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    public function test_full_psgc_import_builds_a_hub_for_every_province_district_and_municipality(): void
    {
        $this->artisan('pickrush:build-hubs --import')->assertSuccessful();

        $this->assertSame(85, Hub::provinces()->count(), '81 provinces + 4 Metro Manila districts');
        $this->assertSame(1634, Hub::stations()->count());
        $this->assertSame(42046, \DB::table('geo_barangays')->count());
        $this->assertSame(0, Hub::where('is_active', false)->count(), 'every hub starts active');
        $this->assertSame(Hub::count(), Hub::distinct()->count('name'), 'hub names are unique');
        $this->assertSame(0, Hub::stations()->whereNull('parent_hub_id')->count(), 'every station sorts through a province hub');

        $parentOf = fn (string $station) => Hub::where('name', $station)->firstOrFail()->parent->name;
        $this->assertSame('Cebu Province Hub', $parentOf('Cebu City Station'));
        $this->assertSame('Basilan Province Hub', $parentOf('Isabela City Station'));
        $this->assertSame('Maguindanao Province Hub', $parentOf('Cotabato City Station'));
        $this->assertSame('Metro Manila Eastern District Hub', $parentOf('Quezon City Station'));
        $this->assertSame('Metro Manila Southern District Hub', $parentOf('Pateros Station'));
        $this->assertSame('Batangas Province Hub', $parentOf('Tanauan City Station'));

        // Re-running is safe: nothing new, nothing duplicated.
        $this->artisan('pickrush:build-hubs')->expectsOutputToContain('(0 new)')->assertSuccessful();
        $this->assertSame(1719, Hub::count());
    }

    public function test_rebuilding_keeps_what_hq_changed(): void
    {
        $this->buildNetwork();
        $tanauan = $this->station('041029000');
        $tanauan->update(['address' => 'J.P. Laurel Hwy, Tanauan', 'is_active' => false]);

        app(HubNetworkBuilder::class)->build();

        $this->assertSame(['J.P. Laurel Hwy, Tanauan', false], [$tanauan->fresh()->address, $tanauan->fresh()->is_active]);
        $this->assertSame('Tanauan City Station', $tanauan->fresh()->name);
    }

    public function test_sample_managers_are_inactive_placeholders_that_can_be_purged(): void
    {
        $this->buildNetwork();
        $real = $this->staff(StaffRole::HubManager, $this->station('043405000'));

        $this->artisan('pickrush:seed-samples')->assertSuccessful();

        $samples = Staff::where('is_sample', true)->get();
        $this->assertCount(Hub::count() - 1, $samples, 'hubs that already have a manager are skipped');
        $this->assertTrue($samples->every(fn (Staff $s) => ! $s->is_active && str_ends_with($s->email, '@pickrush.test')));
        $this->assertContains('No active hub manager', $this->station('041029000')->readinessGaps(), 'a sample does not make a hub ready');
        $this->assertNotContains('No active hub manager', $real->hub->readinessGaps());

        $this->artisan('pickrush:purge-samples')->assertSuccessful();
        $this->assertSame(1, Staff::count());
    }

    public function test_sample_seeder_refuses_production_without_force(): void
    {
        $this->buildNetwork();
        $this->app['env'] = 'production';

        $this->artisan('pickrush:seed-samples')->assertFailed();
        $this->assertSame(0, Staff::count());
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('pickrush:create-admin', ['--name' => 'Hq Admin', '--email' => 'HQ@Pickrush.test', '--password' => 'long-enough-pass'])
            ->assertSuccessful();

        $admin = Staff::sole();
        $this->assertSame(['hq@pickrush.test', StaffRole::SuperAdmin, null], [$admin->email, $admin->role, $admin->hub_id]);

        $this->artisan('pickrush:create-admin', ['--name' => 'Again', '--email' => 'hq@pickrush.test', '--password' => 'long-enough-pass'])
            ->assertFailed();
    }
}
