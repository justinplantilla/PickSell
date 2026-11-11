<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Geo\AddressResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * An approved test buyer living in Cebu City, Cebu, with PSGC codes and a door pin so Pickrush
 * can route deliveries to them.
 *
 *   php artisan db:seed --class=CebuBuyerSeeder
 *
 * Signs in to PickSell as cebu.buyer@picksell.ph / Buyer@1234.
 * Safe to run again: an existing user with that email is left as it is.
 */
class CebuBuyerSeeder extends Seeder
{
    private const EMAIL = 'cebu.buyer@picksell.ph';

    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            $this->command?->warn(self::EMAIL.' already exists; nothing to do.');

            return;
        }

        $codes = app(AddressResolver::class)->resolve('Cebu', 'Cebu City', 'Guadalupe');
        if (! $codes['barangay_code']) {
            throw new RuntimeException('Guadalupe, Cebu City is not in the location tables. Run php artisan pickrush:build-hubs --import first.');
        }

        $buyer = new User([
            'role' => 'buyer',
            'status' => 'approved',
            'last_name' => 'Villanueva',
            'first_name' => 'Andrea',
            'middle_initial' => 'M',
            'sex' => 'Female',
            'email' => self::EMAIL,
            'password' => Hash::make('Buyer@1234'),
            'contact_no' => '09323456789',
            'birthday' => '1998-08-11',
            'age' => 28,
            'province' => 'Cebu',
            'municipality' => 'Cebu City',
            'barangay' => 'Guadalupe',
            'street' => 'V. Rama Ave',
            'house_no' => '218',
            'postal_code' => '6000',
            'delivery_lat' => 10.3221,
            'delivery_lng' => 123.8863,
        ]);
        // The codes route parcels, so they are set here from the lookup, never mass-assigned.
        $buyer->forceFill($codes + ['email_verified_at' => now()])->save();

        $this->command?->info('Buyer '.self::EMAIL.' created in Guadalupe, Cebu City (password Buyer@1234).');
    }
}
