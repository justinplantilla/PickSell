<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Geo\AddressResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One approved test buyer in each of Laguna, Cavite, Batangas, Rizal and Quezon, with PSGC codes
 * and a door pin so Pickrush can route to them. Only the buyers: no hubs, managers or riders are
 * set up for their areas.
 *
 *   php artisan db:seed --class=SouthLuzonBuyersSeeder
 *
 * Each signs in to PickSell with Buyer@1234 (emails below).
 * Safe to run again: an existing user with the same email is left as it is.
 */
class SouthLuzonBuyersSeeder extends Seeder
{
    private const PASSWORD = 'Buyer@1234';

    private const BUYERS = [
        [
            'email' => 'calamba.buyer@picksell.ph', 'first_name' => 'Andrea', 'last_name' => 'Reyes', 'middle_initial' => 'M',
            'sex' => 'Female', 'birthday' => '1998-05-14', 'age' => 28, 'contact_no' => '09451230401',
            'province' => 'Laguna', 'municipality' => 'Calamba', 'barangay' => 'Real',
            'street' => 'Chipeco Ave', 'house_no' => '12', 'postal_code' => '4027',
            'delivery_lat' => 14.2117, 'delivery_lng' => 121.1653,
        ],
        [
            'email' => 'dasmarinas.buyer@picksell.ph', 'first_name' => 'Joshua', 'last_name' => 'Villanueva', 'middle_initial' => 'T',
            'sex' => 'Male', 'birthday' => '1996-08-02', 'age' => 30, 'contact_no' => '09451230402',
            'province' => 'Cavite', 'municipality' => 'Dasmariñas', 'barangay' => 'Salitran I',
            'street' => 'Emilio Aguinaldo Hwy', 'house_no' => '210', 'postal_code' => '4114',
            'delivery_lat' => 14.3326, 'delivery_lng' => 120.9411,
        ],
        [
            'email' => 'batangas.buyer@picksell.ph', 'first_name' => 'Katrina', 'last_name' => 'Aquino', 'middle_initial' => 'S',
            'sex' => 'Female', 'birthday' => '1999-11-27', 'age' => 26, 'contact_no' => '09451230403',
            'province' => 'Batangas', 'municipality' => 'Batangas City', 'barangay' => 'Alangilan',
            'street' => 'Golden Country Homes Rd', 'house_no' => '7', 'postal_code' => '4200',
            'delivery_lat' => 13.7826, 'delivery_lng' => 121.0710,
        ],
        [
            'email' => 'antipolo.buyer@picksell.ph', 'first_name' => 'Miguel', 'last_name' => 'Castillo', 'middle_initial' => 'P',
            'sex' => 'Male', 'birthday' => '1993-02-09', 'age' => 33, 'contact_no' => '09451230404',
            'province' => 'Rizal', 'municipality' => 'Antipolo', 'barangay' => 'San Roque',
            'street' => 'M. L. Quezon St', 'house_no' => '33', 'postal_code' => '1870',
            'delivery_lat' => 14.5866, 'delivery_lng' => 121.1754,
        ],
        [
            'email' => 'lucena.buyer@picksell.ph', 'first_name' => 'Patricia', 'last_name' => 'Gomez', 'middle_initial' => 'A',
            'sex' => 'Female', 'birthday' => '1995-06-30', 'age' => 31, 'contact_no' => '09451230405',
            'province' => 'Quezon', 'municipality' => 'Lucena', 'barangay' => 'Ibabang Dupay',
            'street' => 'Diversion Rd', 'house_no' => '58', 'postal_code' => '4301',
            'delivery_lat' => 13.9471, 'delivery_lng' => 121.6232,
        ],
    ];

    public function run(): void
    {
        $resolver = app(AddressResolver::class);

        foreach (self::BUYERS as $data) {
            if (User::where('email', $data['email'])->exists()) {
                $this->command?->warn("{$data['email']} already exists; left as it is.");

                continue;
            }

            $codes = $resolver->resolve($data['province'], $data['municipality'], $data['barangay']);
            if (! $codes['barangay_code']) {
                $this->command?->error("{$data['barangay']}, {$data['municipality']} is not in the location tables; {$data['email']} skipped. Run php artisan pickrush:build-hubs --import first.");

                continue;
            }

            $buyer = new User($data + [
                'role' => 'buyer',
                'status' => 'approved',
                'password' => Hash::make(self::PASSWORD),
            ]);
            // The codes route parcels, so they are set here from the lookup, never mass-assigned.
            $buyer->forceFill($codes + ['email_verified_at' => now()])->save();

            $this->command?->info("Buyer {$data['email']} created in {$data['barangay']}, {$data['municipality']}, {$data['province']} (password ".self::PASSWORD.').');
        }
    }
}
