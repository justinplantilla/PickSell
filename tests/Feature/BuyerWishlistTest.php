<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\RecentlyViewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerWishlistTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, int $number, string $status = 'approved'): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => "Wish{$number}",
            'email' => "{$role}-wish{$number}@example.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => $status,
            'sex' => 'Male',
            'contact_no' => '0917123456' . $number,
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }

    private function product(User $seller, string $name, array $extra = []): Product
    {
        return Product::create($extra + [
            'seller_id' => $seller->id,
            'name' => $name,
            'category' => 'Home & Living',
            'price' => 500,
            'stock' => 5,
            'status' => 'active',
        ]);
    }

    public function test_buyer_saves_and_removes_a_product_from_the_wishlist(): void
    {
        $seller = $this->user('seller', 1);
        $buyer = $this->user('buyer', 2);
        $lamp = $this->product($seller, 'Desk Lamp');

        $this->actingAs($buyer)->get(route('buyer.product', $lamp))->assertOk()->assertSee('Add to wishlist');

        $this->actingAs($buyer)->post(route('buyer.wishlist.store', $lamp))->assertRedirect();
        $this->actingAs($buyer)->post(route('buyer.wishlist.store', $lamp)); // saving twice keeps one row
        $this->assertSame(1, WishlistItem::where('user_id', $buyer->id)->count());

        $this->actingAs($buyer)->get(route('buyer.product', $lamp))->assertSee('Saved to wishlist');
        $this->actingAs($buyer)->get(route('buyer.wishlist'))->assertOk()->assertSee('Desk Lamp');

        $this->actingAs($buyer)->deleteJson(route('buyer.wishlist.destroy', $lamp))->assertOk()->assertJson(['wishlisted' => false]);
        $this->assertSame(0, WishlistItem::where('user_id', $buyer->id)->count());
        $this->actingAs($buyer)->get(route('buyer.wishlist'))->assertSee('Your wishlist is empty');
    }

    public function test_unavailable_products_cannot_be_saved_and_drop_off_the_wishlist(): void
    {
        $seller = $this->user('seller', 1);
        $buyer = $this->user('buyer', 2);
        $draft = $this->product($seller, 'Hidden Draft', ['status' => 'archived']);
        $mug = $this->product($seller, 'Coffee Mug');

        $this->actingAs($buyer)->post(route('buyer.wishlist.store', $draft))->assertNotFound();

        WishlistItem::create(['user_id' => $buyer->id, 'product_id' => $mug->id]);
        $mug->update(['status' => 'archived']);
        $this->actingAs($buyer)->get(route('buyer.wishlist'))->assertOk()->assertDontSee('Coffee Mug');
    }

    public function test_each_buyer_sees_only_their_own_wishlist(): void
    {
        $seller = $this->user('seller', 1);
        $ana = $this->user('buyer', 2);
        $ben = $this->user('buyer', 3);
        $lamp = $this->product($seller, 'Desk Lamp');
        WishlistItem::create(['user_id' => $ana->id, 'product_id' => $lamp->id]);

        $this->actingAs($ben)->get(route('buyer.wishlist'))->assertOk()->assertDontSee('Desk Lamp');
        $this->actingAs($ben)->delete(route('buyer.wishlist.destroy', $lamp));
        $this->assertSame(1, WishlistItem::where('user_id', $ana->id)->count());
    }

    public function test_only_approved_buyers_reach_the_wishlist(): void
    {
        $seller = $this->user('seller', 1);
        $lamp = $this->product($seller, 'Desk Lamp');

        $this->get(route('buyer.wishlist'))->assertRedirect('/login');
        $this->actingAs($this->user('buyer', 2, 'pending'))->get(route('buyer.wishlist'))->assertForbidden();
        $this->actingAs($seller)->post(route('buyer.wishlist.store', $lamp))->assertRedirect('/dashboard');
        $this->assertSame(0, WishlistItem::count());
    }

    public function test_viewing_a_product_adds_it_to_recently_viewed_on_the_home_page(): void
    {
        $seller = $this->user('seller', 1);
        $buyer = $this->user('buyer', 2);
        $lamp = $this->product($seller, 'Desk Lamp');
        $mug = $this->product($seller, 'Coffee Mug');

        $this->actingAs($buyer)->get(route('buyer.home'))->assertOk()->assertDontSee('Recently Viewed');

        $this->actingAs($buyer)->get(route('buyer.product', $lamp));
        $this->travel(1)->minutes();
        $this->actingAs($buyer)->get(route('buyer.product', $mug));
        $this->travel(1)->minutes();
        $this->actingAs($buyer)->get(route('buyer.product', $lamp)); // re-opening moves it to the front

        $this->assertSame(2, RecentlyViewedProduct::where('user_id', $buyer->id)->count());
        $this->assertSame(['Desk Lamp', 'Coffee Mug'], app(RecentlyViewed::class)->products($buyer)->pluck('name')->all());
        $this->actingAs($buyer)->get(route('buyer.home'))->assertSee('Recently Viewed');
    }

    public function test_recently_viewed_keeps_only_the_newest_entries(): void
    {
        $seller = $this->user('seller', 1);
        $buyer = $this->user('buyer', 2);
        $service = app(RecentlyViewed::class);

        for ($i = 1; $i <= RecentlyViewed::KEEP + 3; $i++) {
            $service->record($buyer, $this->product($seller, "Item {$i}"));
            $this->travel(1)->seconds();
        }

        $this->assertSame(RecentlyViewed::KEEP, RecentlyViewedProduct::where('user_id', $buyer->id)->count());
        $this->assertSame('Item ' . (RecentlyViewed::KEEP + 3), $service->products($buyer, 1)->first()->name);
        $this->assertFalse(RecentlyViewedProduct::whereHas('product', fn ($q) => $q->where('name', 'Item 1'))->exists());
    }
}
