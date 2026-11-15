<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Geo\AddressResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * An approved test buyer living in Santa Cruz, Laguna, with PSGC codes and a door pin so Pickrush
 * can route deliveries to them.
 *
 *   php artisan db:seed --class=SantaCruzBuyerSeeder
 *
 * Signs in to PickSell as santacruz.buyer@picksell.ph / Buyer@1234.
 * Safe to run again: an existing user with that email is left as it is.
 */
class SantaCruzBuyerSeeder extends Seeder
{
    private const EMAIL = 'santacruz.buyer@picksell.ph';

    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            $this->command?->warn(self::EMAIL.' already exists; nothing to do.');

            return;
        }

        $codes = app(AddressResolver::class)->resolve('Laguna', 'Santa Cruz', 'Pagsawitan');
        if (! $codes['barangay_code']) {
            throw new RuntimeException('Pagsawitan, Santa Cruz is not in the location tables. Run php artisan pickrush:build-hubs --import first.');
        }

        $buyer = new User([
            'role' => 'buyer',
            'status' => 'approved',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Marco',
            'middle_initial' => 'R',
            'sex' => 'Male',
            'email' => self::EMAIL,
            'password' => Hash::make('Buyer@1234'),
            'contact_no' => '09451234567',
            'birthday' => '1997-03-22',
            'age' => 29,
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
            'barangay' => 'Pagsawitan',
            'street' => 'National Highway',
            'house_no' => '45',
            'postal_code' => '4009',
            'delivery_lat' => 14.2737,
            'delivery_lng' => 121.4303,
        ]);
        // The codes route parcels, so they are set here from the lookup, never mass-assigned.
        $buyer->forceFill($codes + ['email_verified_at' => now()])->save();

        $this->command?->info('Buyer '.self::EMAIL.' created in Pagsawitan, Santa Cruz (password Buyer@1234).');
    }
}
