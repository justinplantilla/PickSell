<?php

namespace App\Pickrush\Console\Commands;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Development and staging only: one placeholder hub manager per hub so every portal screen has
 * data. Names are randomly combined from common Filipino first names and surnames (no real
 * people), accounts are inactive, cannot sign in, and are flagged is_sample.
 */
class SeedSampleManagers extends Command
{
    protected $signature = 'pickrush:seed-samples {--force : Allow running in production}';

    protected $description = 'Create an inactive sample manager for every hub that has none';

    private const FIRST = ['Juan', 'Maria', 'Jose', 'Ana', 'Mark', 'Kristine', 'Paolo', 'Angelica', 'Rafael', 'Joy',
        'Carlo', 'Patricia', 'Miguel', 'Camille', 'Jerome', 'Lovely', 'Ramon', 'Grace', 'Noel', 'Rowena',
        'Arnel', 'Liza', 'Dennis', 'Mylene', 'Ronaldo', 'Rhea', 'Eduardo', 'Cherry', 'Ariel', 'Jasmine'];

    private const LAST = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Castillo', 'Flores',
        'Villanueva', 'Ramos', 'Aquino', 'Navarro', 'Salazar', 'Mercado', 'Dela Cruz', 'Gonzales', 'Domingo', 'Pascual',
        'Soriano', 'Valdez', 'Aguilar', 'Manalo', 'Lim', 'Tan', 'De Leon', 'Fernandez', 'Robles', 'Morales'];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Sample managers are for development and staging. Pass --force to run in production.');

            return self::FAILURE;
        }

        // One unusable password for all: the accounts are inactive and only exist to fill the screens.
        $password = Hash::make(Str::random(40));
        $created = 0;

        DB::transaction(function () use ($password, &$created) {
            Hub::whereDoesntHave('staff', fn ($q) => $q->where('role', StaffRole::HubManager))
                ->orderBy('id')->get()->each(function (Hub $hub) use ($password, &$created) {
                    mt_srand($hub->id);
                    Staff::create([
                        'full_name' => self::FIRST[mt_rand(0, count(self::FIRST) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)],
                        'email' => 'mgr.'.Str::slug($hub->code).'@pickrush.test',
                        'password' => $password,
                        'role' => StaffRole::HubManager,
                        'hub_id' => $hub->id,
                        'is_active' => false,
                        'is_sample' => true,
                    ]);
                    $created++;
                });
        });
        mt_srand();

        $this->info("{$created} sample managers created (inactive). Remove them with: php artisan pickrush:purge-samples");

        return self::SUCCESS;
    }
}
