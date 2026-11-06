<?php

namespace Tests\Feature;

use App\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class BuyerBrowseFiltersTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_buyers_filter_by_price_rating_sale_and_store_location(): void
    {
        $cebu = $this->seller(['business_name' => 'Cebu Lamps']);                      // same province as the buyer
        $manila = $this->seller(['business_name' => 'Manila Mugs', 'province' => 'Metro Manila']);
        $buyer = $this->buyer();
        $cheap = $this->product($cebu, ['name' => 'Cheap Lamp', 'price' => 200]);
        $deal = $this->product($cebu, ['name' => 'Deal Lamp', 'price' => 1000, 'discount' => 50]);  // ₱500
        $mug = $this->product($manila, ['name' => 'Fancy Mug', 'price' => 800]);
        ProductReview::create(['product_id' => $mug->id, 'buyer_id' => $buyer->id, 'rating' => 5]);
        ProductReview::create(['product_id' => $cheap->id, 'buyer_id' => $buyer->id, 'rating' => 2]);

        $names = fn (array $q) => collect($this->actingAs($buyer)->get(route('buyer.browse', $q))->assertOk()->viewData('products')->items())->pluck('name')->all();

        // Price follows the discount: the ₱1,000 lamp at 50% off is ₱500.
        $this->assertEqualsCanonicalizing(['Deal Lamp', 'Fancy Mug'], $names(['min_price' => 400, 'max_price' => 900]));
        $this->assertSame(['Fancy Mug'], $names(['rating' => 4]));
        $this->assertSame(['Deal Lamp'], $names(['deals' => 1]));
        $this->assertEqualsCanonicalizing(['Cheap Lamp', 'Deal Lamp'], $names(['location' => 'near']));
        $this->assertSame(['Fancy Mug'], $names(['location' => 'Metro Manila']));
        $this->assertSame(['Cheap Lamp', 'Deal Lamp', 'Fancy Mug'], $names(['sort' => 'price_asc']));
        $this->assertSame('Fancy Mug', $names(['sort' => 'rating'])[0]);
        $this->assertSame(['Fancy Mug'], $names(['search' => 'Manila']), 'store names are searchable');

        $this->actingAs($buyer)->get(route('buyer.search', ['search' => 'lamp']))->assertRedirect(route('buyer.browse', ['search' => 'lamp']));
        $this->actingAs($buyer)->get(route('buyer.browse'))->assertSee('Near me (Cebu)')->assertSee('5.0 <small>(1)</small>', false);
    }
}
