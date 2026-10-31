<?php

namespace Database\Seeders;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Rider\RiderApplication;
use App\Pickrush\Services\Station\RiderOnboarding;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * An approved test rider at Paete Station, Laguna, so the station can take deliveries.
 * Goes through the real flow: the rider-app application (with placeholder license and OR/CR
 * files), then approval by the Paete station hub manager.
 *
 *   php artisan db:seed --class=PaeteRiderSeeder
 *
 * Signs in to the Pickrush rider app as paete.rider@pickrush.test / Rider@1234.
 * Safe to run again: an existing rider with that email is left as it is.
 */
class PaeteRiderSeeder extends Seeder
{
    private const EMAIL = 'paete.rider@pickrush.test';

    public function run(): void
    {
        if (Rider::withoutGlobalScopes()->where('email', self::EMAIL)->exists()) {
            $this->command?->warn(self::EMAIL.' already exists; nothing to do.');

            return;
        }

        $station = Hub::where('code', 'S-043418000')->firstOrFail();
        $manager = Staff::where('hub_id', $station->id)->where('role', StaffRole::HubManager)->where('is_active', true)->first()
            ?? throw new RuntimeException('Paete Station needs an active hub manager to approve the rider.');

        $rider = app(RiderApplication::class)->apply([
            'hub_id' => $station->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birth_date' => '1996-05-14',
            'mobile' => '9170418001',
            'email' => self::EMAIL,
            'password' => 'Rider@1234',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043418000',
            'city_name' => 'Paete',
            'barangay_code' => '043418001',
            'barangay_name' => 'Bagumbayan (Pob.)',
            'street' => 'J. P. Rizal St.',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'NAB 4181',
            'license_no' => 'D04-18-000181',
            'license_expiry' => now()->addYears(3)->toDateString(),
        ], [
            'license_front' => UploadedFile::fake()->create('license-front.jpg', 40, 'image/jpeg'),
            'license_back' => UploadedFile::fake()->create('license-back.jpg', 40, 'image/jpeg'),
            'orcr' => [UploadedFile::fake()->create('or-cr.jpg', 40, 'image/jpeg')],
        ]);

        // Approved by the station's own manager, as in the portal (and recorded as theirs).
        auth('pickrush')->setUser($manager);
        app(RiderOnboarding::class)->approve($rider, $manager);

        $this->command?->info("Approved rider #{$rider->id} at {$station->name}: ".self::EMAIL.' / Rider@1234');
    }
}
