<?php

namespace App\Pickrush\Console\Commands;

use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Staff;
use Illuminate\Console\Command;

class PurgeSamples extends Command
{
    protected $signature = 'pickrush:purge-samples';

    protected $description = 'Delete every sample (placeholder) staff account';

    public function handle(): int
    {
        // One bulk delete with one audit entry, instead of an audit row per placeholder.
        $count = Staff::where('is_sample', true)->delete();
        AuditLog::record(null, 'staff.samples_purged', [], ['count' => $count]);
        $this->info("{$count} sample accounts deleted.");

        return self::SUCCESS;
    }
}
