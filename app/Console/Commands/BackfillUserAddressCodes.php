<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Geo\AddressResolver;
use Illuminate\Console\Command;

/** Fills PSGC codes for accounts created before codes were stored, and lists what did not match. */
class BackfillUserAddressCodes extends Command
{
    protected $signature = 'geo:backfill-user-codes {--all : Re-resolve users that already have codes}';

    protected $description = 'Resolve PSGC codes for existing user addresses';

    public function handle(AddressResolver $resolver): int
    {
        $unmatched = [];
        $resolved = 0;

        User::query()
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('barangay_code'))
            ->whereNotNull('municipality')
            ->chunkById(200, function ($users) use ($resolver, &$unmatched, &$resolved) {
                foreach ($users as $user) {
                    $codes = $resolver->resolve($user->province, $user->municipality, $user->barangay);
                    User::whereKey($user->id)->update($codes);   // no model events: the address itself did not change
                    $codes['barangay_code'] ? $resolved++ : $unmatched[] = [$user->id, $user->email, "{$user->barangay}, {$user->municipality}, {$user->province}"];
                }
            });

        $this->info("{$resolved} addresses resolved to a barangay.");
        if ($unmatched) {
            $this->warn(count($unmatched).' addresses need a manual fix:');
            $this->table(['User', 'Email', 'Address'], $unmatched);
        }

        return self::SUCCESS;
    }
}
