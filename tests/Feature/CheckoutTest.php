<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_checkout_selected_cart_items(): void
    {
        $buyer = User::create([
            'role' => 'buyer',
            'status' => 'approved',
            'last_name' => 'Buyer',
            'first_name' => 'Test',
            'middle_initial' => null,
            'sex' => 'Male',
            'email' => 'buyer.checkout@example.com',
            'password' => Hash::make('secret123'),
            'contact_no' => '09123456789',
            'birthday' => '1995-01-01',
            'age' => 31,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Diliman',
            'street' => 'Katipunan Ave',
            'house_no' => '123',
            'id_upload' => 'uploads/test.jpg',
        ]);

        $seller = User::create([
            'role' => 'seller',
            'status' => 'approved',
            'last_name' => 'Seller',
            'first_name' => 'Test',
            'middle_initial' => null,
            'sex' => 'Female',
            'email' => 'seller.checkout@example.com',
            'password' => Hash::make('secret123'),
            'contact_no' => '09987654321',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Taguig',
            'barangay' => 'Fort Bonifacio',
            'street' => 'Main St',
            'house_no' => '9',
            'id_upload' => 'uploads/seller.jpg',
            'business_name' => 'Test Shop',
            'line_of_business' => 'Retail',
            'business_permit' => 'uploads/permit.pdf',
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Sample Product',
            'description' => 'Test product',
            'category' => 'Electronics',
            'price' => 500.00,
            'discount' => 0,
            'voucher_code' => null,
            'voucher_discount' => 0,
            'stock' => 10,
            'status' => 'active',
        ]);

        $cart = Cart::create(['buyer_id' => $buyer->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        config()->set('app.platform_commission_rate', 12.5);

        $logistics = User::create([
            'role' => 'logistics',
            'status' => 'approved',
            'last_name' => 'Logistics',
            'first_name' => 'Test',
            'middle_initial' => null,
            'sex' => 'Male',
            'email' => 'logistics.checkout@example.com',
            'password' => Hash::make('secret123'),
            'contact_no' => '09112223344',
            'birthday' => '1988-02-15',
            'age' => 38,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Diliman',
            'street' => "Teacher's Village",
            'house_no' => '8',
            'id_upload' => 'uploads/logistics.jpg',
            'business_name' => 'Fast Route Logistics',
        ]);

        $this->actingAs($buyer)
            ->get('/buyer/cart/checkout?' . http_build_query(['item_ids' => [$item->id]]))
            ->assertOk()
            ->assertSee('Checkout');

        $response = $this->actingAs($buyer)
            ->from('/buyer/cart')
            ->post('/buyer/cart/checkout', [
                'item_ids' => [$item->id],
                'payment_method' => 'cod',
                'logistics_id' => $logistics->id,
            ]);

        $response->assertRedirect('/buyer/orders');
        $this->assertDatabaseHas('orders', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'logistics_id' => $logistics->id,
            'quantity' => 1,
            'status' => 'placed',
            'commission' => 62.50,
            'commission_rate' => 12.50,
        ]);
        $orderId = \App\Models\Order::where('buyer_id', $buyer->id)->value('id');

        $this->assertDatabaseCount('financial_transactions', 0);
        \Illuminate\Support\Facades\DB::table('orders')->where('id', $orderId)->update(['status' => 'delivered']);
        $this->actingAs($seller)
            ->patch(route('seller.orders.confirm-delivery', $orderId))
            ->assertRedirect();

        $this->assertDatabaseHas('financial_transactions', [
            'order_id' => $orderId,
            'seller_id' => $seller->id,
            'type' => 'order_gross',
            'debit' => 500,
            'credit' => 0,
            'amount' => 500,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('financial_transactions', [
            'order_id' => $orderId,
            'seller_id' => $seller->id,
            'type' => 'commission',
            'debit' => 0,
            'credit' => 62.50,
            'amount' => 62.50,
        ]);
        $this->assertDatabaseHas('financial_transactions', [
            'order_id' => $orderId,
            'seller_id' => $seller->id,
            'type' => 'seller_net',
            'debit' => 0,
            'credit' => 437.50,
            'amount' => 437.50,
        ]);
        $debits = \App\Models\FinancialTransaction::where('order_id', $orderId)->sum('debit');
        $credits = \App\Models\FinancialTransaction::where('order_id', $orderId)->sum('credit');
        $this->assertSame(500.0, (float) $debits);
        $this->assertSame(500.0, (float) $credits);

        config(['app.platform_commission_rate' => 20]);
        $money = app(\App\Services\Finance\FinancialSummary::class)->forOrder(\App\Models\Order::findOrFail($orderId));
        $this->assertSame(12.5, $money['commission_rate']);
        $this->assertSame(62.5, $money['commission']);
        $this->assertSame(437.5, $money['net_to_seller']);
    }

    public function test_commission_rounds_to_minor_units_before_calculating_seller_net(): void
    {
        config(['app.platform_commission_rate' => 10]);
        $calculation = app(\App\Services\CommissionService::class)->calculate('0.05');

        $this->assertSame([
            'rate' => '10.00',
            'commission' => '0.01',
            'seller_net' => '0.04',
        ], $calculation);
    }
}
