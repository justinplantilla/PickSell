<?php

namespace App\Pickrush\Console\Commands;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** Creates the first Pickrush HQ super admin; everyone else is created from the portal. */
class CreateAdmin extends Command
{
    protected $signature = 'pickrush:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Create a Pickrush super admin account';

    public function handle(): int
    {
        $data = [
            'full_name' => $this->option('name') ?: $this->ask('Full name'),
            'email' => strtolower(trim($this->option('email') ?: $this->ask('Email'))),
            'password' => $this->option('password') ?: $this->secret('Password (at least 10 characters)'),
        ];

        $validator = Validator::make($data, [
            'full_name' => 'required|string|max:120',
            'email' => 'required|email|unique:pr_staff,email',
            'password' => 'required|string|min:10',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Staff::create($data + ['role' => StaffRole::SuperAdmin, 'hub_id' => null, 'is_active' => true]);
        $this->info("Super admin {$data['email']} created. Sign in at https://".config('pickrush.domain').'/login');

        return self::SUCCESS;
    }
}
