<?php

namespace App\Pickrush\Console\Commands;

use App\Pickrush\Services\HubNetworkBuilder;
use Illuminate\Console\Command;

class BuildHubs extends Command
{
    protected $signature = 'pickrush:build-hubs {--import : Load the PSGC data into the geo tables first}';

    protected $description = 'Create a province hub per province/district and a station hub per city/municipality';

    public function handle(HubNetworkBuilder $builder): int
    {
        if ($this->option('import')) {
            $this->call('geo:import-psgc');
        }

        $result = $builder->build();
        $this->info("Hub network ready: {$result['provinces']} province hubs, {$result['stations']} station hubs ({$result['created']} new).");

        return self::SUCCESS;
    }
}
