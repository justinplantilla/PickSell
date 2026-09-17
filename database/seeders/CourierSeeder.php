<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CourierSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            'Santa Cruz, Laguna',
            'Pagsanjan, Laguna',
            'Los Baños, Laguna',
            'Calamba, Laguna',
            'Biñan, Laguna',
            'San Pablo, Laguna',
            'Cabuyao, Laguna',
            'Bay, Laguna',
            'Liliw, Laguna',
            'Sta. Rosa, Laguna',
        ];

        foreach ($areas as $index => $area) {
            $number = $index + 1;
            User::updateOrCreate(
                ['email' => "rider{$number}@picksell.test"],
                [
                    'role' => 'courier',
                    'status' => 'approved',
                    'last_name' => 'Rider',
                    'first_name' => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'sex' => $number % 2 === 0 ? 'Female' : 'Male',
                    'email' => "rider{$number}@picksell.test",
                    'password' => Hash::make('Rider@1234'),
                    'contact_no' => '0917000' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'birthday' => '1995-01-01',
                    'age' => 31,
                    'province' => 'Laguna',
                    'municipality' => explode(',', $area)[0],
                    'barangay' => 'Poblacion',
                    'street' => 'Main Road',
                    'house_no' => (string) (100 + $number),
                    'id_upload' => null,
                    'vehicle_type' => $number % 3 === 0 ? 'Car' : 'Motorcycle',
                    'plate_number' => 'RDR ' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'or_cr_upload' => null,
                    'delivery_area' => $area,
                ]
            );
        }

        foreach ($areas as $index => $area) {
            $number = $index + 1;
            User::updateOrCreate(
                ['email' => "applicant{$number}@picksell.test"],
                [
                    'role' => 'courier',
                    'status' => 'pending',
                    'last_name' => 'Applicant',
                    'first_name' => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'sex' => $number % 2 === 0 ? 'Female' : 'Male',
                    'email' => "applicant{$number}@picksell.test",
                    'password' => Hash::make('Applicant@1234'),
                    'contact_no' => '0918000' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'birthday' => '1996-02-02',
                    'age' => 30,
                    'province' => 'Laguna',
                    'municipality' => explode(',', $area)[0],
                    'barangay' => 'Poblacion',
                    'street' => 'Applicant Street',
                    'house_no' => (string) (200 + $number),
                    'id_upload' => null,
                    'vehicle_type' => $number % 3 === 0 ? 'Car' : 'Motorcycle',
                    'plate_number' => 'APP ' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'or_cr_upload' => null,
                    'delivery_area' => $area,
                ]
            );
        }
    }
}
