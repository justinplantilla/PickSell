<?php

namespace Tests\Feature\BuyerApi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Following stores, as on the website's store pages and Following list (SellerFollows). */
class FollowingTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_buyers_follow_and_unfollow_stores_and_list_them(): void
    {
        $lamps = $this->seller(['business_name' => 'Lamp Shop']);
        $mugs = $this->seller(['business_name' => 'Mug House']);
        $this->product($lamps);
        $this->product($lamps, ['name' => 'Archived Lamp', 'status' => 'archived']);
        $this->actingAsBuyer();

        $this->getJson('/api/mobile/buyer/following')->assertOk()->assertJsonPath('stores', [])->assertJsonPath('meta.total', 0);

        $this->postJson("/api/mobile/buyer/sellers/{$lamps->id}/follow")->assertOk()->assertExactJson(['following' => true, 'followers' => 1]);
        $this->postJson("/api/mobile/buyer/sellers/{$lamps->id}/follow")->assertOk()->assertExactJson(['following' => true, 'followers' => 1], 'following twice changes nothing');
        $this->travel(1)->minutes();
        $this->postJson("/api/mobile/buyer/sellers/{$mugs->id}/follow")->assertOk();

        $list = $this->getJson('/api/mobile/buyer/following')->assertOk()->json();
        $this->assertSame(['Mug House', 'Lamp Shop'], array_column($list['stores'], 'name'), 'newest follow first, as on the website');
        $this->assertSame([0, 1], array_column($list['stores'], 'active_products'));
        $this->assertSame(['id', 'name', 'location', 'verified', 'on_vacation', 'logo', 'active_products', 'followed_at'], array_keys($list['stores'][0]));
        $this->assertSame(2, $list['meta']['total']);

        $this->deleteJson("/api/mobile/buyer/sellers/{$mugs->id}/follow")->assertOk()->assertExactJson(['following' => false, 'followers' => 0]);
        $this->deleteJson("/api/mobile/buyer/sellers/{$mugs->id}/follow")->assertOk()->assertJsonPath('following', false);
        $this->getJson('/api/mobile/buyer/following')->assertJsonPath('meta.total', 1);
    }

    public function test_the_store_page_says_whether_you_follow_it(): void
    {
        $seller = $this->seller();
        $this->getJson("/api/mobile/sellers/{$seller->id}")->assertOk()
            ->assertJsonPath('seller.followers', 0)->assertJsonPath('seller.is_following', false);

        $buyer = $this->actingAsBuyer();
        $this->postJson("/api/mobile/buyer/sellers/{$seller->id}/follow")->assertOk();
        $this->getJson("/api/mobile/sellers/{$seller->id}")->assertOk()
            ->assertJsonPath('seller.followers', 1)->assertJsonPath('seller.is_following', true);

        // Another buyer sees the count but isn't following.
        Sanctum::actingAs($this->buyer());
        $this->getJson("/api/mobile/sellers/{$seller->id}")->assertJsonPath('seller.followers', 1)->assertJsonPath('seller.is_following', false);
        $this->assertTrue($buyer->followedSellers()->whereKey($seller->id)->exists());
    }

    public function test_only_approved_stores_can_be_followed(): void
    {
        $pending = $this->person('seller', ['status' => 'pending']);
        $buyer = $this->buyer();
        $this->actingAsBuyer();

        $this->postJson("/api/mobile/buyer/sellers/{$pending->id}/follow")->assertNotFound();
        $this->postJson("/api/mobile/buyer/sellers/{$buyer->id}/follow")->assertNotFound();
        $this->postJson('/api/mobile/buyer/sellers/999999/follow')->assertNotFound();
        $this->deleteJson('/api/mobile/buyer/sellers/999999/follow')->assertNotFound();
    }
}
