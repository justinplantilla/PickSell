<?php

namespace App\Services;

use App\Models\LogisticsBranch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class HubOperatorService
{
    public function create(LogisticsBranch $hub, array $data): User
    {
        $location = $hub->municipality()->firstOrFail();

        return User::create([
            'role' => 'logistics',
            'status' => 'approved',
            'assigned_hub_id' => $hub->id,
            'first_name' => trim($data['operator_first_name']),
            'last_name' => trim($data['operator_last_name']),
            'middle_initial' => null,
            'sex' => $data['operator_sex'],
            'email' => strtolower(trim($data['operator_email'])),
            'password' => Hash::make($data['operator_password']),
            'contact_no' => $data['operator_contact_no'],
            'birthday' => $data['operator_birthday'],
            'age' => Carbon::parse($data['operator_birthday'])->age,
            'province' => $location->province,
            'municipality' => $location->name,
            'barangay' => 'Hub Operations',
            'street' => null,
            'house_no' => null,
            'id_upload' => null,
        ]);
    }
}
