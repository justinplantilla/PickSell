<?php

namespace Database\Seeders;

use App\Models\Geo\Municipality;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Hq\HubCreator;
use App\Pickrush\Support\AccountContacts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Test hub managers for MobLoop in Metro Manila: one at its Eastern District province hub (the
 * Second District) and one at its Pasig City station. Opens either hub first when MobLoop has
 * not opened it yet.
 *
 *   php artisan db:seed --class=MobLoopManagersSeeder
 *
 * Sign in to the logistics portal with the emails below and the password Manager@1234.
 * Safe to run again: an existing account is left as it is.
 */
class MobLoopManagersSeeder extends Seeder
{
    private const COMPANY = 'MobLoop';

    private const PASSWORD = 'Manager@1234';

    private const SECOND_DISTRICT = '137400000';

    public function run(): void
    {
        $company = Company::where('name', self::COMPANY)->where('status', 'approved')->first();
        if (! $company) {
            $this->command?->error('No approved company named '.self::COMPANY.'. Approve it in Admin → Logistics first.');

            return;
        }
        $pasig = Municipality::where('province_code', self::SECOND_DISTRICT)->where('name', 'like', '%Pasig%')->firstOrFail();

        DB::transaction(function () use ($company, $pasig) {
            $hubs = app(HubCreator::class);
            $district = Hub::ofCompany($company->id)->provinces()->where('psgc_code', self::SECOND_DISTRICT)->first()
                ?? $hubs->province($company->id, self::SECOND_DISTRICT);
            $station = Hub::ofCompany($company->id)->stations()->where('psgc_code', $pasig->code)->first()
                ?? $hubs->station($company->id, $pasig->code);

            $this->manager($company, $district, 'Eastern District Manager', 'eastern.manager@mobloop.test', '09170004001');
            $this->manager($company, $station, 'Pasig Station Manager', 'pasig.manager@mobloop.test', '09170004002');
        });
    }

    private function manager(Company $company, Hub $hub, string $name, string $email, string $mobile): void
    {
        if (AccountContacts::emailTaken($email)) {
            $this->command?->warn("{$email} already exists; left as it is.");

            return;
        }

        Staff::create([
            'company_id' => $company->id,
            'full_name' => $name,
            'email' => $email,
            'mobile_no' => AccountContacts::mobileTaken($mobile) ? null : $mobile,
            'password' => self::PASSWORD,
            'role' => StaffRole::HubManager,
            'hub_id' => $hub->id,
            'is_active' => true,
            'is_sample' => false,
            'email_verified_at' => now(),
        ]);
        $this->command?->info("{$hub->name}: {$email} / ".self::PASSWORD);
    }
}
