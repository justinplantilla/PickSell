<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CartBulkRemoveTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, array $extra = []): User
    {
        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'last_name' => 'User', 'first_name' => 'Test', 'sex' => 'Male',
            'email' => $email, 'password' => Hash::make('secret123'), 'contact_no' => '09123456789',
            'birthday' => '1995-01-01', 'age' => 31, 'province' => 'Metro Manila', 'municipality' => 'Quezon City',
            'barangay' => 'Diliman', 'street' => 'Katipunan Ave', 'house_no' => '1', 'id_upload' => 'uploads/test.jpg',
        ], $extra));
    }

    public function test_buyer_removes_several_selected_items_at_once_but_never_another_buyers(): void
    {
        $seller = $this->user('seller', 'seller.bulk@example.com', ['business_name' => 'Bulk Shop', 'line_of_business' => 'Retail', 'business_permit' => 'uploads/permit.pdf']);
        $buyer = $this->user('buyer', 'buyer.bulk@example.com');
        $other = $this->user('buyer', 'other.bulk@example.com');
        $items = collect(range(1, 3))->map(function ($n) use ($seller, $buyer) {
            $product = Product::create(['seller_id' => $seller->id, 'name' => "Item {$n}", 'description' => 'x', 'category' => 'Electronics',
                'price' => 100, 'discount' => 0, 'voucher_discount' => 0, 'stock' => 5, 'status' => 'active', 'weight_kg' => 1]);

            return CartItem::create(['cart_id' => Cart::firstOrCreate(['buyer_id' => $buyer->id])->id, 'product_id' => $product->id, 'quantity' => 1]);
        });
        $othersItem = CartItem::create(['cart_id' => Cart::create(['buyer_id' => $other->id])->id, 'product_id' => $items[0]->product_id, 'quantity' => 1]);

        $this->actingAs($buyer)->get('/buyer/cart')->assertOk()->assertSee('data-cart-bulk-delete', false);
        $this->actingAs($buyer)->deleteJson('/buyer/cart/items', ['item_ids' => [$items[0]->id, $items[1]->id, $othersItem->id]])
            ->assertOk()->assertJson(['removed' => 2, 'message' => '2 items removed from cart.']);

        $this->assertSame([$items[2]->id], CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $buyer->id))->pluck('id')->all());
        $this->assertNotNull($othersItem->fresh());
        $this->actingAs($buyer)->deleteJson('/buyer/cart/items', ['item_ids' => []])->assertUnprocessable();
    }
}
