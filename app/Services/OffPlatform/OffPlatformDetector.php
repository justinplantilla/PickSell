<?php

namespace App\Services\OffPlatform;

use App\Models\PlatformSetting;

/**
 * Finds signs of a sale being taken off PickSell in free text: contact details (phone, email,
 * links, social accounts, account numbers), outside payment, outside couriers, and phrases such
 * as "meet up" or "text me". Contact details can also be masked so they never reach the other
 * party. Admins add their own watched keywords in Platform Settings (one per line).
 */
class OffPlatformDetector
{
    public const KEYWORDS_SETTING = 'off_platform_keywords';

    /** Masked: what lets two people reach each other or pay outside PickSell. */
    private const MASKED = [
        'email' => '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
        'link' => '/\b(?:https?:\/\/|www\.)\S+|\b(?:fb\.com|facebook\.com|fb\.me|m\.me|instagram\.com|t\.me|wa\.me|tiktok\.com|shopee\.ph|lazada\.com\.ph)\/?\S*/i',
        // Not inside a tracking number (PR2611…) or another code: no letter or digit right next to it.
        'phone' => '/(?<![\w-])(?:\+?63[\s.-]?|0)9\d{2}[\s.-]?\d{3}[\s.-]?\d{4}(?![\w-])/',
        'account' => '/(?<![\w-])(?:\d[\s-]?){9,15}\d(?![\w-])/',
    ];

    /** Flagged only: words that are fine to read but worth an admin's look. */
    private const WATCHED = [
        'payment' => '/\b(?:g-?cash|pay\s?maya|maya\s+(?:wallet|app)|bdo|bpi|metrobank|unionbank|palawan\s+express|cebuana|western\s+union|remittance)\b/i',
        'courier' => '/\b(?:lbc|j\s?&\s?t|jnt|j\s?and\s?t|jrs|lalamove|grab\s?express|grab\s?delivery|flash\s?express|ninja\s?van|ninjavan|2go|xde|entrego|abest|air21)\b/i',
        'link' => '/\b(?:viber|telegram|whats\s?app|messenger|instagram|ig\s*:|fb\s*:|facebook\s+page)\b/i',
        'phrase' => '/\b(?:meet\s?-?up|sa\s+labas|outside\s+(?:of\s+)?pick\s?sell|direct\s+(?:deal|transaction|payment|order)|deal\s+direct|text\s+(?:me|mo\s+ako)|pm\s+(?:me|mo\s+ako)|add\s+me\s+on|chat\s+me\s+on|call\s+me|tawagan\s+mo\s+ako|cash\s+on\s+pick\s?up|personal\s+number)\b/i',
    ];

    /** @return list<array{signal: string, match: string}> each finding once, contact details first */
    public function scan(?string $text): array
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return [];
        }
        $found = [];
        $masked = $text;
        foreach (self::MASKED as $signal => $pattern) {
            if (preg_match_all($pattern, $masked, $matches)) {
                foreach ($matches[0] as $match) {
                    $found[] = ['signal' => $signal, 'match' => trim($match)];
                }
                // An email or link is not then counted again as a phone or account number.
                $masked = preg_replace($pattern, ' ', $masked);
            }
        }
        foreach (self::WATCHED as $signal => $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $found[] = ['signal' => $signal, 'match' => trim($match)];
                }
            }
        }
        foreach ($this->keywords() as $keyword) {
            if (mb_stripos($text, $keyword) !== false) {
                $found[] = ['signal' => 'keyword', 'match' => $keyword];
            }
        }

        return collect($found)->unique(fn ($f) => $f['signal'].'|'.mb_strtolower($f['match']))->values()->all();
    }

    /** The text with contact details hidden: 0917 123 4567 → 09•• ••• ••67. */
    public function mask(string $text): string
    {
        foreach (self::MASKED as $pattern) {
            $text = preg_replace_callback($pattern, function (array $m) {
                $value = $m[0];
                $keep = mb_substr(preg_replace('/\s+/', '', $value), -2);

                return preg_replace('/[^\s]/u', '•', mb_substr($value, 0, max(0, mb_strlen($value) - 2))).$keep;
            }, $text);
        }

        return $text;
    }

    /** @return list<string> admin-added watched keywords */
    public function keywords(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', PlatformSetting::get(self::KEYWORDS_SETTING, '')))
            ->map(fn ($k) => trim($k))->filter(fn ($k) => mb_strlen($k) >= 3)->unique()->values()->all();
    }
}
