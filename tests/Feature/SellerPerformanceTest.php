<?php

namespace Tests\Feature;

use App\Services\SellerPerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerPerformanceTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_reliable_well_rated_sellers_become_preferred(): void
    {
        $good = $this->seller(['business_name' => 'Good Shop']);
        $flaky = $this->seller(['business_name' => 'Flaky Shop']);
        $buyer = $this->buyer();
        $lamp = $this->product($good);
        $mug = $this->product($flaky);
        foreach (range(1, 10) as $i) {
            $this->order($buyer, $lamp, 'completed', ['seller_rating' => 5]);
        }
        foreach (range(1, 4) as $i) {
            $this->order($buyer, $mug, 'cancelled');
        }
        $this->order($buyer, $mug, 'completed', ['seller_rating' => 2]);
        $this->order($buyer, $mug, 'completed', ['seller_rating' => 3]);
        $this->order($buyer, $mug, 'completed', ['seller_rating' => 2]);

        $performance = app(SellerPerformance::class);
        $this->assertTrue($performance->isPreferred($good));
        $flakyStats = $performance->for($flaky);
        $this->assertFalse($flakyStats['preferred']);
        $this->assertSame(['Cancels 57.1% of orders', 'Low service rating (2.3)'], $flakyStats['warnings']);

        $this->actingAs($buyer)->get(route('buyer.seller', $good))->assertOk()->assertSee('Preferred Seller');
        $this->actingAs($buyer)->get(route('buyer.seller', $flaky))->assertOk()->assertDontSee('✓ Preferred Seller');

        $page = $this->actingAs($this->admin())->get(route('admin.seller-performance', ['filter' => 'warnings']))->assertOk();
        $page->assertSee('Flaky Shop')->assertDontSee('Good Shop')->assertSee('Cancels 57.1% of orders');
    }
}
