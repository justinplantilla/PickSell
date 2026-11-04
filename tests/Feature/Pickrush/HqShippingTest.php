<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Models\ShippingRate;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Hq\PolicySettings;
use App\Pickrush\Services\Shipping\RegionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HqShippingTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Staff $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->admin = $this->staff(StaffRole::SuperAdmin);
    }

    private function as(Staff $staff): static
    {
        return $this->actingAs($staff, 'pickrush');
    }

    public function test_hq_sees_rates_classes_provinces_and_finance_can_only_read(): void
    {
        $this->as($this->admin)->get($this->pickrushUrl('/hq/shipping'))->assertOk()
            ->assertSee('Marketplace shipping')->assertSee('BULKY')->assertSee('Quezon City')->assertSee('Add tier fee');

        $finance = $this->staff(StaffRole::Finance);
        $this->as($finance)->get($this->pickrushUrl('/hq/shipping'))->assertOk()->assertDontSee('Add tier fee');
        $this->as($finance)->post($this->pickrushUrl('/hq/shipping/rates'), [])->assertForbidden();
    }

    public function test_a_new_rate_ends_the_old_one_the_day_before_it_starts(): void
    {
        $from = today()->addDays(10)->toDateString();
        $this->as($this->admin)->post($this->pickrushUrl('/hq/shipping/rates'), [
            'destination_region' => 'NCR', 'max_weight_kg' => 1, 'fee' => 80, 'effective_from' => $from,
        ])->assertSessionHasNoErrors();

        $rows = ShippingRate::whereNull('origin_region')->where('destination_region', 'NCR')->where('max_weight_kg', 1)->orderBy('effective_from')->get();
        $this->assertSame([today()->addDays(9)->toDateString(), null], $rows->map(fn ($r) => $r->effective_to?->toDateString())->all());
        $this->assertSame(['75.00', '80.00'], $rows->map(fn ($r) => (string) $r->fee)->all());

        // A second price for the same tier and day is refused.
        $this->as($this->admin)->post($this->pickrushUrl('/hq/shipping/rates'), [
            'destination_region' => 'NCR', 'max_weight_kg' => 1, 'fee' => 90, 'effective_from' => $from,
        ])->assertSessionHasErrors('effective_from');
    }

    public function test_class_rider_pay_cannot_go_below_the_minimum_and_the_minimum_cannot_strand_a_class(): void
    {
        $form = ['min_kg_exclusive' => 10, 'max_kg_inclusive' => 20, 'is_active' => 1];

        $this->as($this->admin)->put($this->pickrushUrl('/hq/shipping/classes/HEAVY'), $form + ['rider_pay' => 14])->assertSessionHasErrors('class_HEAVY');
        $this->assertSame('25.00', (string) ParcelClass::find('HEAVY')->rider_pay);

        $this->as($this->admin)->put($this->pickrushUrl('/hq/shipping/classes/HEAVY'), $form + ['rider_pay' => 30])->assertSessionHasNoErrors();
        $this->assertSame('30.00', (string) ParcelClass::find('HEAVY')->rider_pay);
        $this->assertTrue(AuditLog::where('action', 'parcel_class.HEAVY.updated')->exists());

        // STANDARD pays 15, so the floor cannot rise to 20 until STANDARD does.
        $settings = collect(PolicySettings::FIELDS)->mapWithKeys(fn ($f, $key) => [str_replace('.', '__', $key) => config("pickrush.{$key}")])->all();
        $this->as($this->admin)->put($this->pickrushUrl('/hq/settings'), ['shipping__min_rider_pay' => 20] + $settings)->assertSessionHasErrors('shipping__min_rider_pay');
        $this->assertSame(15, (int) config('pickrush.shipping.min_rider_pay'));
    }

    public function test_provinces_can_be_mapped_and_moved_between_regions(): void
    {
        $this->as($this->admin)->post($this->pickrushUrl('/hq/shipping/provinces'), ['name' => '  PALAWAN ', 'region_code' => 'LUZON'])->assertSessionHasNoErrors();
        $this->assertSame('LUZON', DB::table('pr_shipping_region_provinces')->where('name', 'Palawan')->value('region_code'), 'moved, not duplicated');

        $this->as($this->admin)->post($this->pickrushUrl('/hq/shipping/provinces'), ['name' => 'Kalookan', 'region_code' => 'NCR'])->assertSessionHasNoErrors();
        $this->assertSame('NCR', app(RegionResolver::class)->resolve('kalookan'));
    }
}
