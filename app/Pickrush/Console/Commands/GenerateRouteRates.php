<?php

namespace App\Pickrush\Console\Commands;

use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Shipping\RouteRateGenerator;
use Illuminate\Console\Command;

class GenerateRouteRates extends Command
{
    protected $signature = 'pickrush:generate-route-rates {--from='.RouteRateGenerator::FIRST_ROUTE_DATE.' : First day the generated prices apply}';

    protected $description = 'Generate the marketplace price of every (seller region → buyer region) route from the price levels and route grid';

    public function handle(RouteRateGenerator $generator): int
    {
        try {
            $counts = $generator->generate((string) $this->option('from'));
        } catch (OperationRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Route prices from {$this->option('from')}: {$counts['created']} created, {$counts['ended']} ended, {$counts['updated']} corrected, {$counts['unchanged']} unchanged.");

        return self::SUCCESS;
    }
}
