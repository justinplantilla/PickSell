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

        // Sellers registered before seller types existed (no seller_type) were asked for a business permit only.
        if ($applicant->role === 'seller' && $applicant->seller_type === null) {
            $items['business_permit'] = ['Business permit is valid and names this business', 'business_permit'];
            $items['business_details'] = ['Business name and line of business are consistent with the permit', null];
        } elseif ($applicant->role === 'seller') {
            $items['seller_type'] = ['Seller type matches the documents provided', null];
            $items['tin'] = ['TIN has 12 digits and belongs to this applicant or business', null];
            foreach (SellerTypes::requiredDocuments($applicant->seller_type) as $document) {
                $items[$document] = [SellerTypes::DOCUMENT_LABELS[$document].' is valid and names this business', $document];
            }
            $items['business_details'] = ['Shop name and line of business are consistent with the documents', null];
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
            ...SellerTypes::DOCUMENT_LABELS,
        ])->filter(fn ($label, $field) => filled($applicant->{$field}))
            ->map(fn ($label, $field) => ['label' => $label, 'path' => $applicant->{$field}])
            ->all();
    }
}
