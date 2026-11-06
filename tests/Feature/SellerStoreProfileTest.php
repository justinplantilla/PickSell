<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerStoreProfileTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_sellers_brand_their_storefront(): void
    {
        Storage::fake('public');
        $seller = $this->seller(['business_name' => 'Lamp Shop']);
        $lamp = $this->product($seller);

        $this->actingAs($seller)->post(route('seller.account.store-profile'), [
            'store_logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'store_banner' => UploadedFile::fake()->image('banner.jpg', 1200, 300),
            'store_description' => 'Handmade lamps from Cebu.',
        ])->assertSessionHasNoErrors();

        $seller->refresh();
        Storage::disk('public')->assertExists([$seller->store_logo, $seller->store_banner]);

        $buyer = $this->buyer();
        $this->actingAs($buyer)->get(route('buyer.seller', $seller))->assertOk()
            ->assertSee('Handmade lamps from Cebu.')->assertSee($seller->store_logo_url, false)->assertSee($seller->store_banner_url, false);
        $this->actingAs($buyer)->get(route('buyer.product', $lamp))->assertOk()->assertSee($seller->store_logo_url, false);

        // Replacing the logo deletes the old file; removing the banner clears it.
        $oldLogo = $seller->store_logo;
        $this->actingAs($seller)->post(route('seller.account.store-profile'), [
            'store_logo' => UploadedFile::fake()->image('new.png'), 'remove_store_banner' => 1, 'store_description' => 'Handmade lamps from Cebu.',
        ])->assertSessionHasNoErrors();
        $seller->refresh();
        Storage::disk('public')->assertMissing($oldLogo);
        $this->assertNull($seller->store_banner);

        $this->actingAs($seller)->post(route('seller.account.store-profile'), ['store_logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('store_logo');
        $this->actingAs($seller)->get(route('seller.account'))->assertOk()->assertSee('Store Profile');
    }
}
