<?php

namespace Tests\Feature;

use App\Pickrush\Services\Shipping\DeliveryEstimate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class DeliveryEstimateTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_checkout_shows_when_each_parcel_should_arrive(): void
    {
        Carbon::setTestNow('2026-11-06 10:00:00');
        $buyer = $this->buyer();
        $local = $this->cartLine($buyer, $this->product($this->seller(['business_name' => 'Cebu Shop'])));
        $far = $this->cartLine($buyer, $this->product($this->seller(['business_name' => 'Manila Shop', 'province' => 'Metro Manila'])));
        $farLevel = DB::table('pr_shipping_route_levels')->where(['origin_region' => 'NCR', 'destination_region' => 'VISAYAS'])->value('level');
        [$min, $max] = config('pickrush.shipping.delivery_days')[$farLevel];

        // Cebu to Cebu is a local route: 1–2 days.
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$local->id, $far->id]]))->assertOk()
            ->assertSee('Arrives Nov 7–8')
            ->assertSee('Arrives '.DeliveryEstimate::label(CarbonImmutable::today()->addDays($min), CarbonImmutable::today()->addDays($max)));

        $this->actingAsBuyer($buyer);
        $this->postJson('/api/mobile/buyer/checkout/quote', ['item_ids' => [$local->id]])->assertOk()
            ->assertJsonPath('quote.groups.0.shipping.estimated_delivery', ['from' => '2026-11-07', 'to' => '2026-11-08', 'label' => 'Nov 7–8']);
    }

    public function test_labels_span_months(): void
    {
        $this->assertSame('Nov 30 – Dec 3', DeliveryEstimate::label(CarbonImmutable::parse('2026-11-30'), CarbonImmutable::parse('2026-12-03')));
    }
}
