<?php

namespace App\Console\Commands;

use App\Services\OffPlatform\OffPlatformMonitor;
use Illuminate\Console\Command;

class ScanOffPlatformOrders extends Command
{
    protected $signature = 'off-platform:scan-orders';

    protected $description = 'Flag orders and sellers that suggest sales are being taken off PickSell';

    public function handle(OffPlatformMonitor $monitor): int
    {
        $this->info($monitor->scanOrders().' off-platform signal(s) raised.');

        return self::SUCCESS;
    }
}
