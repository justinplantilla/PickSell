<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerInventorySortingTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::create([
            'first_name' => 'Inventory',
            'last_name' => 'Seller',
            'email' => 'inventory-seller@example.test',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }

    public function test_seller_can_sort_inventory_by_name_price_stock_and_creation_date(): void
    {
        $older = $this->product('Zulu Lamp', 800, 8);
        $middle = $this->product('Mango Bag', 500, 3);
        $newer = $this->product('Alpha Cup', 200, 1);
        $older->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->save();
        $middle->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
        $newer->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        $this->actingAs($this->seller)
            ->get(route('seller.inventory', ['sort' => 'name_asc']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha Cup', 'Mango Bag', 'Zulu Lamp']);

        $this->actingAs($this->seller)
            ->get(route('seller.inventory', ['sort' => 'price_desc']))
            ->assertOk()
            ->assertSeeInOrder(['Zulu Lamp', 'Mango Bag', 'Alpha Cup']);

        $this->actingAs($this->seller)
            ->get(route('seller.inventory', ['sort' => 'stock_asc']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha Cup', 'Mango Bag', 'Zulu Lamp']);

        $this->actingAs($this->seller)
            ->get(route('seller.inventory', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSeeInOrder(['Zulu Lamp', 'Mango Bag', 'Alpha Cup']);
    }

    public function test_unknown_inventory_sort_falls_back_to_newest_and_filters_are_preserved(): void
    {
        $older = $this->product('Older Item', 100, 2);
        $newer = $this->product('Newer Item', 200, 8);
        $older->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        $this->actingAs($this->seller)
            ->get(route('seller.inventory', [
                'sort' => 'invalid',
                'search' => 'Item',
                'status' => 'active',
                'filter' => 'all',
            ]))
            ->assertOk()
            ->assertSeeInOrder(['Newer Item', 'Older Item'])
            ->assertSee('value="newest" selected', false)
            ->assertSee('value="Item"', false);
    }

    private function product(string $name, int $price, int $stock): Product
    {
        return Product::create([
            'seller_id' => $this->seller->id,
            'name' => $name,
            'category' => 'General',
            'price' => $price,
            'stock' => $stock,
            'status' => 'active',
        ]);
    }
}
