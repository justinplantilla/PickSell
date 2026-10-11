<?php

namespace Tests\Feature;

use App\Models\BranchRider;
use App\Models\DeliveryLog;
use App\Models\FinancialTransaction;
use App\Models\LogisticsBranch;
use App\Models\LogisticsException;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use Database\Seeders\AdminDemoDataSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_demo_seeder_builds_connected_operations_and_is_repeatable(): void
    {
        $this->seed(AdminDemoDataSeeder::class);

        $staleParcel = Order::where('order_number', 'DEMO-2026-005')->firstOrFail();
        $unassignedParcel = Order::where('order_number', 'DEMO-2026-006')->firstOrFail();
        $failedParcel = Order::where('order_number', 'DEMO-2026-009')->firstOrFail();
        $completedParcel = Order::where('order_number', 'DEMO-2026-011')->firstOrFail();
        $disputedParcel = Order::where('order_number', 'DEMO-2026-010')->firstOrFail();

        $this->assertSame('at_sorting_center', $staleParcel->status);
        $this->assertTrue($staleParcel->updated_at->lt(now()->subHours(48)));
        $this->assertNotNull($staleParcel->delivery);
        $this->assertTrue($staleParcel->delivery->logs->isNotEmpty());

        $this->assertSame('sorted', $unassignedParcel->status);
        $this->assertNull($unassignedParcel->courier_id);
        $this->assertNotNull($unassignedParcel->destinationBranch);
        $this->assertNotNull($unassignedParcel->destinationBarangay);

        $this->assertSame('delivery_failed', $failedParcel->status);
        $this->assertSame(2, $failedParcel->delivery->delivery_attempts);
        $this->assertSame('completed', $completedParcel->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $completedParcel->id,
            'to_status' => 'completed',
        ]);
        $this->assertDatabaseHas('delivery_assignments', [
            'delivery_id' => $failedParcel->delivery->id,
            'rider_id' => $failedParcel->courier_id,
        ]);

        $this->assertSame('open', LogisticsException::where('order_id', $failedParcel->id)->value('status'));
        $this->assertSame('open', ReturnRequest::where('order_id', $disputedParcel->id)->value('dispute_status'));
        $this->assertDatabaseHas('refunds', ['order_id' => Order::where('order_number', 'DEMO-2026-012')->value('id'), 'status' => 'approved']);
        $this->assertGreaterThan(0, FinancialTransaction::where('order_id', $completedParcel->id)->count());
        $this->assertDatabaseHas('product_reviews', ['order_id' => $completedParcel->id, 'rating' => 5]);
        $this->assertDatabaseHas('complaints', ['order_id' => $failedParcel->id, 'status' => 'open']);
        $this->assertDatabaseHas('messages', [
            'sender_id' => User::where('email', 'buyer.pasig@demo.picksell.test')->value('id'),
            'receiver_id' => User::where('email', 'seller.pasig@demo.picksell.test')->value('id'),
        ]);
        $this->assertGreaterThan(0, DeliveryLog::where('delivery_id', $failedParcel->delivery->id)->count());
        $this->assertGreaterThan(0, BranchRider::where('status', 'active')->count());

        $counts = [
            Order::count(),
            LogisticsBranch::count(),
            LogisticsException::count(),
            ReturnRequest::count(),
            Refund::count(),
            FinancialTransaction::count(),
        ];

        $this->seed(AdminDemoDataSeeder::class);

        $this->assertSame($counts, [
            Order::count(),
            LogisticsBranch::count(),
            LogisticsException::count(),
            ReturnRequest::count(),
            Refund::count(),
            FinancialTransaction::count(),
        ]);
        $this->assertSame(1, ProductReview::where('order_id', $completedParcel->id)->count());
    }

    public function test_production_seeding_requires_explicit_opt_in(): void
    {
        $originalEnvironment = app()->environment();
        $originalOptIn = getenv('ADMIN_DEMO_DATA_ALLOW_PRODUCTION');
        app()->instance('env', 'production');
        putenv('ADMIN_DEMO_DATA_ALLOW_PRODUCTION');

        try {
            try {
                $this->app->make(AdminDemoDataSeeder::class)->run();
                $this->fail('Production seeding must require explicit opt-in.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('ADMIN_DEMO_DATA_ALLOW_PRODUCTION=true', $exception->getMessage());
            }

            $this->assertSame(0, Order::where('order_number', 'like', 'DEMO-2026-%')->count());

            putenv('ADMIN_DEMO_DATA_ALLOW_PRODUCTION=true');
            $this->app->make(AdminDemoDataSeeder::class)->run();

            $this->assertSame(14, Order::where('order_number', 'like', 'DEMO-2026-%')->count());
        } finally {
            if ($originalOptIn === false) {
                putenv('ADMIN_DEMO_DATA_ALLOW_PRODUCTION');
            } else {
                putenv('ADMIN_DEMO_DATA_ALLOW_PRODUCTION='.$originalOptIn);
            }
            app()->instance('env', $originalEnvironment);
        }
    }

    public function test_default_local_database_seeder_can_be_repeated_and_admin_pages_render(): void
    {
        $this->seed(DatabaseSeeder::class);
        $initialOrderCount = Order::count();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($initialOrderCount, Order::count());
        $admin = User::where('email', 'admin@picksell.ph')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Marketplace lifecycle')
            ->assertSee('Operational exceptions');

        $this->get(route('admin.logistics.index'))
            ->assertOk()
            ->assertSee('Pasig Demo Hub')
            ->assertSee('DEMO-2026-009');
        $this->get(route('admin.complaints'))->assertOk()->assertSee('Delivery attempt needs follow-up');
        $this->get(route('admin.disputes'))->assertOk()->assertSee('DEMO-2026-010');
        $this->get(route('admin.products', ['search' => 'Archived Demo Listing', 'filter_status' => 'archived']))
            ->assertOk()
            ->assertSee('Archived Demo Listing');
        $this->get(route('admin.reports'))->assertOk();
    }
}
