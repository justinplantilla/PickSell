<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerCategoryBrowseTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, int $number): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => "Browse{$number}",
            'email' => "{$role}-browse{$number}@example.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }

    private function product(User $seller, string $name, string $category): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'name' => $name,
            'category' => $category,
            'price' => 500,
            'stock' => 5,
            'status' => 'active',
        ]);
    }

    private function order(User $buyer, User $seller, Product $product, int $quantity, int $number): void
    {
        Order::create([
            'order_number' => "BROWSE-{$number}",
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'amount' => $product->price * $quantity,
            'commission' => 0,
            'status' => 'delivered',
        ]);
    }

    public function test_buyer_shop_promotions_and_top_category_products_render(): void
    {
        $buyer = $this->user('buyer', 1);
        $seller = $this->user('seller', 2);
        $popularSpeaker = $this->product($seller, 'Best-selling speaker', 'Electronics');
        $otherSpeaker = $this->product($seller, 'Portable speaker', 'Electronics');
        $polo = $this->product($seller, 'Classic polo', 'Clothing');
        $this->order($buyer, $seller, $popularSpeaker, 6, 1);
        $this->order($buyer, $seller, $otherSpeaker, 2, 2);
        $this->order($buyer, $seller, $polo, 1, 3);

        $this->actingAs($buyer)
            ->get(route('buyer.home'))
            ->assertOk()
            ->assertSee('Flash ')
            ->assertSee('deals')
            ->assertSee('Top Picks by Category')
            ->assertSeeInOrder(['Best-selling speaker', 'Classic polo'])
            ->assertSee(route('buyer.categories'), false)
            ->assertSee(route('buyer.browse', ['deals' => 1]), false);
    }

    public function test_category_browser_filters_products_and_shows_category_sidebar(): void
    {
        $buyer = $this->user('buyer', 1);
        $seller = $this->user('seller', 2);
        $this->product($seller, 'Portable speaker', 'Electronics');
        $this->product($seller, 'Classic polo', 'Clothing');

        $this->actingAs($buyer)
            ->get(route('buyer.categories', ['category' => 'Electronics']))
            ->assertOk()
            ->assertSee('Portable speaker')
            ->assertSee('Clothing')
            ->assertDontSee('Classic polo');
    }
}
