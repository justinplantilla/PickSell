<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Geo\Municipality;
use App\Pickrush\Http\Requests\ResubmitApplicationRequest;
use App\Pickrush\Services\Registration\AddressOptions;
use App\Pickrush\Services\Registration\CompanyRegistration;
use App\Pickrush\Support\Actor;

/** A rejected company corrects its registration and sends it back for review. */
class ApplicationController extends Controller
{
    public function edit()
    {
        $staff = Actor::staff();
        $company = $staff->company;
        abort_unless($company?->isRejected() && $company->owner_staff_id === $staff->id, 403);

        // Metro Manila is one "province" in the dropdown; its cities sit under districts.
        $municipality = $company->municipality_code ? Municipality::find($company->municipality_code) : null;
        $province = $municipality?->region_code === '130000000' ? AddressOptions::METRO_MANILA : $company->province_code;

        return view('pickrush.auth.register', [
            'mode' => 'resubmit',
            'rejectionReason' => $company->rejection_reason,
            'currentDocuments' => collect(['valid_id', 'dti_permit'])->mapWithKeys(fn ($type) => [$type => $company->latestDocument($type)?->original_name])->filter()->all(),
            'values' => [
                'email' => $staff->email,
                'last_name' => $staff->last_name,
                'first_name' => $staff->first_name,
                'middle_initial' => $staff->middle_initial,
                'sex' => $staff->sex,
                'contact_no' => $staff->mobile_no,
                'birthday' => $staff->birthday ? substr((string) $staff->birthday, 0, 10) : null,
                'business_name' => $company->name,
                'position' => $staff->position,
                'province' => $province,
                'municipality' => $company->municipality_code,
                'barangay' => $company->barangay_code,
                'street_address' => $company->street_address,
            ],
        ]);
    }

    public function update(ResubmitApplicationRequest $request, CompanyRegistration $registration)
    {
        $staff = Actor::staff();
        abort_unless($staff->company->owner_staff_id === $staff->id, 403);

        $registration->resubmit($staff->company, $staff, $request->validated(), [
            'valid_id' => $request->file('valid_id'),
            'dti_permit' => $request->file('dti_permit'),
        ]);

        return redirect()->route('pickrush.dashboard')->with('success', 'Your registration was sent back for review.');
    }
}
