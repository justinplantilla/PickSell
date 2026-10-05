<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_download_waybill_label(): void
    {
        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => 'seller@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09170000001',
            'birthday' => '1994-01-01',
            'age' => 32,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SELLER-1',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Premium Sneakers',
            'quantity' => 1,
            'amount' => 1200,
            'commission' => 120,
            'status' => 'shipped',
            'waybill_number' => 'WB-0001',
            'tracking_status' => 'Out for delivery',
        ]);

        $this->actingAs($seller)
            ->get('/seller/orders/' . $order->id . '/waybill')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_seller_can_confirm_delivery(): void
    {
        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => 'seller2@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234568',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => 'buyer2@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09170000002',
            'birthday' => '1994-01-01',
            'age' => 32,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SELLER-2',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Desk Lamp',
            'quantity' => 2,
            'amount' => 800,
            'commission' => 80,
            'status' => 'completed',
            'waybill_number' => 'WB-0002',
            'tracking_status' => 'Delivered',
            'delivered_at' => now(),
        ]);

        $this->actingAs($seller)
            ->patch('/seller/orders/' . $order->id . '/confirm-delivery')
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('Seller confirmed delivery', $order->tracking_status);
        $this->assertNotNull($order->confirmed_by_seller_at);
    }

    public function test_new_order_notifies_seller(): void
    {
        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => 'seller3@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234569',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $order = Order::make([
            'order_number' => 'ORD-SELLER-3',
            'product_name' => 'Travel Backpack',
            'quantity' => 1,
            'amount' => 1500,
            'commission' => 150,
            'status' => 'pending',
        ]);

        $seller->notify(new NewSellerOrder($order));

        $this->assertNotNull($seller->notifications()->first());
    }

    public function test_seller_dashboard_renders_operational_summary(): void
    {
        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'business_name' => 'Nayon Goods',
            'email' => 'seller4@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234570',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => 'buyer4@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09170000004',
            'birthday' => '1994-01-01',
            'age' => 32,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);

        Order::create([
            'order_number' => 'ORD-SELLER-4',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Desk Lamp',
            'quantity' => 1,
            'amount' => 500,
            'commission' => 50,
            'status' => 'placed',
        ]);

        Order::create([
            'order_number' => 'ORD-SELLER-5',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Travel Backpack',
            'quantity' => 1,
            'amount' => 1500,
            'commission' => 150,
            'status' => 'completed',
        ]);

        foreach (['out_for_delivery', 'cancelled', 'delivery_failed', 'returned'] as $index => $status) {
            Order::create([
                'order_number' => 'ORD-SELLER-DIST-' . $index,
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'product_name' => 'Status Test Product',
                'quantity' => 1,
                'amount' => 100,
                'commission' => 10,
                'status' => $status,
            ]);
        }

        \App\Models\Product::create([
            'seller_id' => $seller->id,
            'name' => 'Low Stock Product',
            'category' => 'Accessories',
            'price' => 200,
            'stock' => 2,
            'status' => 'active',
        ]);

        \App\Models\Product::create([
            'seller_id' => $seller->id,
            'name' => 'Healthy Stock Product',
            'category' => 'Accessories',
            'price' => 300,
            'stock' => 20,
            'status' => 'active',
        ]);

        $dashboardResponse = $this->actingAs($seller)->get('/seller/dashboard');
        $dashboardResponse
            ->assertOk()
            ->assertSee('Good')
            ->assertSee(now()->format('l, F j, Y'))
            ->assertSee('Needs Attention')
            ->assertSee(route('seller.inventory', ['action' => 'create']))
            ->assertSee(route('seller.orders', ['status' => 'action_required']))
            ->assertSee(route('seller.orders', ['status' => 'completed']))
            ->assertSee(route('seller.orders', ['status' => 'pending']))
            ->assertSee(route('seller.orders'))
            ->assertSee(route('seller.inventory', ['status' => 'active']))
            ->assertSee(route('seller.inventory', ['filter' => 'low-stock']))
            ->assertSee('Business health')
            ->assertSee('Order pipeline')
            ->assertSee('Catalog health')
            ->assertSee('Processing')
            ->assertSee('Shipped')
            ->assertSee('Delivered')
            ->assertSee('Cancelled / returned')
            ->assertSee(route('seller.orders', ['status' => 'processing']))
            ->assertSee(route('seller.orders', ['status' => 'shipped']))
            ->assertSee(route('seller.orders', ['status' => 'delivered']))
            ->assertSee(route('seller.orders', ['status' => 'cancelled']))
            ->assertSee('Cancelled, delivery failed, or returned')
            ->assertSee('50.0%', false)
            ->assertSee('Pending orders')
            ->assertSee('7 Days')
            ->assertSee('30 Days')
            ->assertSee('6 Months')
            ->assertSee('Unfulfilled orders')
            ->assertSee('Inventory below threshold')
            ->assertSee('Needs your attention')
            ->assertSee('Recent orders')
            ->assertSee('Low-stock inventory');

        $this->assertSame(5, substr_count($dashboardResponse->getContent(), 'class="order-row"'));
        $this->assertMatchesRegularExpression('/<a href="[^"]*\/seller\/orders\/\d+" class="order-row"/', $dashboardResponse->getContent());
        $this->assertStringContainsString('seller-order-status--processing', $dashboardResponse->getContent());

        $this->get(route('seller.orders', ['status' => 'action_required']))
            ->assertOk()
            ->assertSee('ORD-SELLER-4')
            ->assertDontSee('ORD-SELLER-5');

        $this->get(route('seller.orders', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('ORD-SELLER-4')
            ->assertDontSee('ORD-SELLER-5');

        $this->get(route('seller.orders', ['status' => 'shipped']))
            ->assertOk()
            ->assertSee('ORD-SELLER-DIST-0')
            ->assertDontSee('ORD-SELLER-DIST-1');

        $this->get(route('seller.orders', ['status' => 'delivered']))
            ->assertOk()
            ->assertSee('ORD-SELLER-5')
            ->assertDontSee('ORD-SELLER-DIST-0');

        $this->get(route('seller.orders', ['status' => 'cancelled']))
            ->assertOk()
            ->assertSee('ORD-SELLER-DIST-1')
            ->assertSee('ORD-SELLER-DIST-2')
            ->assertSee('ORD-SELLER-DIST-3')
            ->assertDontSee('ORD-SELLER-DIST-0');

        $this->get(route('seller.dashboard', ['range' => '7D', 'metric' => 'orders']))
            ->assertOk()
            ->assertSee('data-chart-metric="orders"', false)
            ->assertSee('data-chart-labels=', false)
            ->assertSee('data-chart-visual', false);

        $this->get(route('seller.inventory', ['filter' => 'low-stock']))
            ->assertOk()
            ->assertSee('Low Stock Product')
            ->assertDontSee('Healthy Stock Product');

        Order::where('seller_id', $seller->id)->delete();
        \App\Models\Product::where('seller_id', $seller->id)->delete();

        $this->get('/seller/dashboard')
            ->assertOk()
            ->assertSee('No activity for this period')
            ->assertSee('No order activity yet')
            ->assertSee('No recent orders')
            ->assertSee('Catalog fully stocked')
            ->assertSee('You&#039;re all caught up', false);
    }

    public function test_seller_can_review_and_export_order_level_earnings(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-05 12:00:00'));

        $seller = User::create([
            'first_name' => 'Earnings',
            'last_name' => 'Seller',
            'email' => 'earnings-seller@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234571',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $otherSeller = User::create([
            'first_name' => 'Other',
            'last_name' => 'Seller',
            'email' => 'other-earnings-seller@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234572',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $buyer = User::create([
            'first_name' => 'Earnings',
            'last_name' => 'Buyer',
            'email' => 'earnings-buyer@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09170000005',
            'birthday' => '1994-01-01',
            'age' => 32,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);

        foreach ([
            ['ORD-EARNINGS-1', $seller->id, 'completed', 1500, 150, '2026-10-04 10:00:00'],
            ['ORD-EARNINGS-2', $seller->id, 'completed', 2000, 200, '2026-10-03 10:00:00'],
            ['ORD-EARNINGS-CANCELLED', $seller->id, 'cancelled', 500, 50, '2026-10-04 11:00:00'],
            ['ORD-EARNINGS-OUTSIDE', $seller->id, 'completed', 900, 90, '2026-10-05 10:00:00'],
            ['ORD-EARNINGS-OTHER-SELLER', $otherSeller->id, 'completed', 700, 70, '2026-10-04 12:00:00'],
        ] as [$orderNumber, $sellerId, $status, $amount, $commission, $createdAt]) {
            $order = Order::create([
                'order_number' => $orderNumber,
                'buyer_id' => $buyer->id,
                'seller_id' => $sellerId,
                'product_name' => 'Earnings Test Product',
                'quantity' => 1,
                'amount' => $amount,
                'commission' => $commission,
                'status' => $status,
            ]);
            \Illuminate\Support\Facades\DB::table('orders')
                ->where('id', $order->id)
                ->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        }

        $filters = [
            'preset' => 'custom',
            'from' => '2026-10-01',
            'to' => '2026-10-04',
        ];

        $this->actingAs($seller)
            ->get(route('seller.earnings', $filters))
            ->assertOk()
            ->assertSee('₱3,500.00')
            ->assertSee('-₱350.00')
            ->assertSee('₱3,150.00')
            ->assertSee('10.00%')
            ->assertSee('ORD-EARNINGS-1')
            ->assertSee('ORD-EARNINGS-2')
            ->assertSee('aria-expanded="false"', false)
            ->assertSee(route('seller.orders.show', Order::where('order_number', 'ORD-EARNINGS-1')->value('id')))
            ->assertDontSee('ORD-EARNINGS-CANCELLED')
            ->assertDontSee('ORD-EARNINGS-OUTSIDE')
            ->assertDontSee('ORD-EARNINGS-OTHER-SELLER');

        $this->get(route('seller.earnings', ['preset' => 'last_7_days']))
            ->assertOk()
            ->assertSee('₱4,400.00')
            ->assertSee('ORD-EARNINGS-OUTSIDE')
            ->assertDontSee('ORD-EARNINGS-OTHER-SELLER');

        $csvResponse = $this->get(route('seller.earnings.csv', $filters));
        $csvResponse->assertOk()
            ->assertDownload('seller-earnings-2026-10-01-to-2026-10-04.csv');
        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString('ORD-EARNINGS-1,2026-10-04,1500.00,10.00,150.00,1350.00,completed', $csv);
        $this->assertStringContainsString('ORD-EARNINGS-1', $csv);
        $this->assertStringContainsString('ORD-EARNINGS-2', $csv);
        $this->assertStringNotContainsString('ORD-EARNINGS-CANCELLED', $csv);
        $this->assertStringNotContainsString('ORD-EARNINGS-OUTSIDE', $csv);
        $this->assertStringNotContainsString('ORD-EARNINGS-OTHER-SELLER', $csv);

        $this->get(route('seller.earnings.pdf', $filters))
            ->assertOk()
            ->assertDownload('seller-earnings-2026-10-01-to-2026-10-04.pdf');

        config()->set('app.platform_commission_rate', 12.5);
        $this->get(route('seller.earnings', $filters))
            ->assertOk()
            ->assertSee('-₱437.50')
            ->assertSee('₱3,062.50')
            ->assertSee('-₱187.50')
            ->assertSee('(12.50%)');

        $this->get(route('seller.reports', ['from' => $filters['from'], 'to' => $filters['to']]))
            ->assertOk()
            ->assertSee('₱3,500.00')
            ->assertSee('-₱437.50')
            ->assertSee('₱3,062.50')
            ->assertSee('Net Earnings (After Commission)')
            ->assertSee('Sales and Net Earnings by Order');

        $reportCsv = $this->get(route('seller.reports.csv', ['from' => $filters['from'], 'to' => $filters['to']]));
        $reportCsv->assertOk()->assertDownload('seller-report-2026-10-01-to-2026-10-04.csv');
        $this->assertStringContainsString(',1500.00,12.50,187.50,1312.50,completed', $reportCsv->streamedContent());

        $this->get(route('seller.reports.pdf', ['from' => $filters['from'], 'to' => $filters['to']]))
            ->assertOk()
            ->assertDownload('seller-report-2026-10-01-to-2026-10-04.pdf');

        $this->get(route('seller.earnings', [
            'preset' => 'custom',
            'from' => '2026-10-05',
            'to' => '2026-10-04',
        ]))->assertSessionHasErrors('to');
    }
}
