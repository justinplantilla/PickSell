<?php

namespace App\Support;

use App\Models\User;

/**
 * What an Admin must verify before approving an application, per role. Items tied to a document
 * cannot be confirmed when that document is missing, so such applications can only be disapproved.
 */
final class RegistrationChecklist
{
    /** @return array<string, array{label: string, document: ?string, available: bool}> */
    public static function for(User $applicant): array
    {
        $items = [
            'identity' => ['Government ID is readable and matches the applicant’s name and birthday', 'id_upload'],
            'contact' => ['Email address and mobile number are valid', null],
            'address' => ['Address is complete and plausible', null],
        ];

        if ($applicant->role === 'seller') {
            $items['business_permit'] = ['Business permit is valid and names this business', 'business_permit'];
            $items['business_details'] = ['Business name and line of business are consistent with the permit', null];
        }

        return collect($items)->map(fn ($item) => [
            'label' => $item[0],
            'document' => $item[1],
            'available' => $item[1] === null || filled($applicant->{$item[1]}),
        ])->all();
    }

    /** Uploaded documents to show reviewers, labelled. */
    public static function documents(User $applicant): array
    {
        return collect([
            'id_upload' => 'Government-issued ID',
            'business_permit' => 'Business permit',
        ])->filter(fn ($label, $field) => filled($applicant->{$field}))
            ->map(fn ($label, $field) => ['label' => $label, 'path' => $applicant->{$field}])
            ->all();
    }
}
