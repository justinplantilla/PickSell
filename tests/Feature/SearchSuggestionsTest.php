<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SearchSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_suggestions_include_image_url_and_effective_price(): void
    {
        Storage::fake('public');
        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'Suggestions',
            'email' => 'seller-suggestions@example.test',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Everyday backpack',
            'category' => 'Sports',
            'price' => 1000,
            'discount' => 10,
            'stock' => 5,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'products/backpack.jpg',
            'display_order' => 1,
            'is_primary' => true,
        ]);

        $this->getJson('/api/search-suggestions?q=backpack')
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Everyday backpack')
            ->assertJsonPath('products.0.category', 'Sports')
            ->assertJsonPath('products.0.image', Storage::url('products/backpack.jpg'))
            ->assertJsonPath('products.0.price', 900);
    }
}
