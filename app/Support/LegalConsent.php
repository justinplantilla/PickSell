<?php

namespace App\Support;

/**
 * Proof that an account agreed to the Terms of Service and Privacy Policy: the version (config
 * legal.version) and the moment. Stamped at every sign-up (website, buyer app, rider app,
 * logistics company registration), always with forceFill: it is never taken from the request.
 */
final class LegalConsent
{
    /** @return array{terms_version: string, terms_accepted_at: \Illuminate\Support\Carbon} */
    public static function stamp(): array
    {
        return ['terms_version' => (string) config('legal.version'), 'terms_accepted_at' => now()];
    }
}
