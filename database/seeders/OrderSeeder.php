<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\Finance\FinancialLedgerService;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $buyers = User::where('role', 'buyer')->where('status', 'approved')->pluck('id');
        $sellers = User::where('role', 'seller')->where('status', 'approved')->pluck('id');

        if ($buyers->isEmpty() || $sellers->isEmpty()) {
            $this->command->warn('No approved buyers/sellers found. Skipping order seeder.');

            return;
        }

        $products = ['Laptop Bag', 'Wireless Mouse', 'USB Hub', 'Phone Case', 'Desk Lamp', 'Notebook Set', 'Headphones', 'Keyboard'];
        $commissionService = app(CommissionService::class);
        $ledger = app(FinancialLedgerService::class);

        for ($i = 1; $i <= 20; $i++) {
            $amount = rand(500, 5000);
            $calculation = $commissionService->calculate((string) $amount);
            $order = Order::create([
                'order_number' => 'ORD-'.str_pad($i, 4, '0', STR_PAD_LEFT),
                'buyer_id' => $buyers->random(),
                'seller_id' => $sellers->random(),
                'product_name' => $products[array_rand($products)],
                'amount' => $amount,
                'commission' => $calculation['commission'],
                'commission_rate' => $calculation['rate'],
                'status' => 'completed',
                'created_at' => now()->subDays(rand(0, 30)),
                'updated_at' => now(),
            ]);
            $ledger->postCompletedOrder($order);
        }
    }
}
