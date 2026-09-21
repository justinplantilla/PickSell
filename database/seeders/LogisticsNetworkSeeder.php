<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\BranchRider;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\RiderBarangay;
use App\Models\User;
use Illuminate\Database\Seeder;

class LogisticsNetworkSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereNotNull('province')
            ->whereNotNull('municipality')
            ->whereNotNull('barangay')
            ->get();

        foreach ($users->groupBy(fn (User $user) => mb_strtolower(trim($user->province)).'|'.mb_strtolower(trim($user->municipality))) as $locationUsers) {
            $sample = $locationUsers->first();
            $municipality = Municipality::firstOrCreate(
                ['province' => trim($sample->province), 'name' => trim($sample->municipality)],
                ['code' => null],
            );

            $branch = LogisticsBranch::firstOrCreate(
                ['municipality_id' => $municipality->id, 'name' => trim($sample->municipality).' Branch'],
                ['logistics_id' => $locationUsers->firstWhere('role', 'logistics')?->id, 'status' => 'active'],
            );

            foreach ($locationUsers->groupBy(fn (User $user) => mb_strtolower(trim($user->barangay))) as $barangayUsers) {
                $barangay = Barangay::firstOrCreate([
                    'municipality_id' => $municipality->id,
                    'name' => trim($barangayUsers->first()->barangay),
                ]);

                foreach ($barangayUsers as $user) {
                    $user->forceFill([
                        'municipality_id' => $municipality->id,
                        'barangay_id' => $barangay->id,
                    ])->saveQuietly();

                    if ($user->role !== 'courier' || $user->status !== 'approved') continue;

                    $assignment = BranchRider::firstOrCreate(
                        ['branch_id' => $branch->id, 'user_id' => $user->id],
                        ['status' => 'active'],
                    );

                    RiderBarangay::firstOrCreate([
                        'branch_rider_id' => $assignment->id,
                        'barangay_id' => $barangay->id,
                    ], ['is_primary' => true]);
                }
            }
        }
    }
}