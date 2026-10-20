<?php

namespace App\Support;

/**
 * Seller types and the business documents each must upload besides the government-issued ID:
 * individuals need only the ID, sole proprietors add DTI and BIR certificates, corporations SEC and BIR.
 */
final class SellerTypes
{
    public const LABELS = [
        'individual' => 'Individual',
        'sole_proprietorship' => 'Sole Proprietorship',
        'corporation' => 'Corporation',
    ];

    public const DOCUMENT_LABELS = [
        'dti_certificate' => 'DTI Certificate of Business Name Registration',
        'sec_certificate' => 'SEC Certificate of Incorporation',
        'bir_certificate' => 'BIR Certificate of Registration (Form 2303)',
    ];

    private const DOCUMENTS = [
        'individual' => [],
        'sole_proprietorship' => ['dti_certificate', 'bir_certificate'],
        'corporation' => ['sec_certificate', 'bir_certificate'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    /** Document fields this seller type must upload. @return list<string> */
    public static function requiredDocuments(?string $type): array
    {
        return self::DOCUMENTS[$type] ?? [];
    }

    /** Seller types that must upload this document field. @return list<string> */
    public static function typesRequiring(string $document): array
    {
        return array_keys(array_filter(self::DOCUMENTS, fn (array $documents) => in_array($document, $documents, true)));
    }

    public static function label(?string $type): ?string
    {
        return self::LABELS[$type] ?? null;
    }
}
