<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class BuyerStoreSearchTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_global_search_finds_approved_stores_and_links_to_their_profiles(): void
    {
        $store = $this->seller(['business_name' => 'Juan Appliances']);
        $this->seller(['business_name' => 'Juan Pending', 'status' => 'pending']);
        $response = $this->actingAs($this->buyer())->get(route('buyer.browse', ['search' => 'Juan']));
        $response->assertOk()->assertSee(route('buyer.seller', $store))
            ->assertSee('Followers')->assertSee('Following')->assertSee('Seller rating')
            ->assertSee('No ratings yet')->assertSee('Not enough data')->assertSee('On PickSell since')
            ->assertSee(route('buyer.seller.follow', $store))->assertSee('Message');
        $this->assertSame([$store->id], $response->viewData('stores')->pluck('id')->all());
        $this->getJson('/api/search-suggestions?q=Juan')->assertOk()
            ->assertJsonCount(1, 'stores')->assertJsonPath('stores.0.name', 'Juan Appliances')
            ->assertJsonPath('stores.0.url', route('buyer.seller', $store));
    }

    public function test_store_search_and_suggestions_exclude_other_sellers_products(): void
    {
        $store = $this->seller();
        $other = $this->seller();
        $product = $this->product($store, ['name' => 'Store Blender']);
        $this->product($store, ['name' => 'Store Kettle']);
        $this->product($other, ['name' => 'Other Blender']);
        $this->actingAs($this->buyer());
        $response = $this->get(route('buyer.seller', ['seller' => $store, 'search' => 'Blender']));
        $response->assertOk()->assertSee('This store')->assertSee('All PickSell');
        $this->assertSame([$product->id], $response->viewData('products')->pluck('id')->all());
        $this->getJson('/api/search-suggestions?q=Blender&seller_id='.$store->id)->assertOk()
            ->assertJsonCount(1, 'products')->assertJsonPath('products.0.name', 'Store Blender')
            ->assertJsonCount(0, 'stores');
    }

    public function test_seller_suggestions_match_partial_store_and_owner_names(): void
    {
        $store = $this->seller(['business_name' => 'Juan Appliances', 'first_name' => 'Ricardo', 'last_name' => 'Santos']);
        $this->seller(['business_name' => 'Juan Unapproved', 'status' => 'pending']);
        foreach (['ua', 'appl', 'APPL', 'icar', 'anto'] as $fragment) {
            $this->getJson('/api/search-suggestions?q='.urlencode($fragment))->assertOk()
                ->assertJsonCount(1, 'stores')
                ->assertJsonPath('stores.0.url', route('buyer.seller', $store));
        }
    }

    public function test_scope_routes_search_to_store_or_all_picksell(): void
    {
        $store = $this->seller();
        $this->actingAs($this->buyer());
        $this->get(route('buyer.search', ['scope' => 'store', 'seller_id' => $store->id, 'search' => 'Lamp']))
            ->assertRedirect(route('buyer.seller', ['seller' => $store, 'search' => 'Lamp']));
        $this->get(route('buyer.search', ['scope' => 'all', 'seller_id' => $store->id, 'search' => 'Lamp']))
            ->assertRedirect(route('buyer.browse', ['search' => 'Lamp']));
    }

    public function test_chat_performance_ignores_auto_replies_and_new_inquiries(): void
    {
        $this->freezeTime();
        $store = $this->seller();
        $quick = $this->buyer();
        $automatic = $this->buyer();
        $late = $this->buyer();
        $new = $this->buyer();
        $send = function ($from, $to, $body, $time) {
            $message = new \App\Models\Message(['sender_id' => $from->id, 'receiver_id' => $to->id, 'body' => $body]);
            $message->created_at = $time;
            $message->save();
        };
        foreach ([$quick, $automatic, $late] as $buyer) {
            $send($buyer, $store, 'Is this available?', now()->subDays(3));
        }
        $send($store, $quick, 'Yes, available', now()->subDays(3)->addHour());
        $send($quick, $store, 'Thank you', now()->subDays(2));
        $send($store, $automatic, '[Auto-reply] Thanks for your message', now()->subDays(3)->addMinute());
        $send($store, $late, 'Sorry for the delay', now()->subDay());
        $send($new, $store, 'New inquiry', now()->subHour());
        $this->assertSame(['conversations' => 3, 'rate' => 33], app(\App\Services\SellerChatPerformance::class)->for($store));
    }
}
