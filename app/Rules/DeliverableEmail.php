<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An address a sign-up code can actually reach. Catches typos of the big mailbox providers
 * (gmail.net, gmial.com), which some typo domains even accept mail for, and domains with no
 * mail server at all. The DNS lookup can be switched off (config mail.verify_domains) for tests.
 */
class DeliverableEmail implements ValidationRule
{
    /** Provider name => the domain people mean. */
    private const PROVIDERS = [
        'gmail' => 'gmail.com', 'yahoo' => 'yahoo.com', 'outlook' => 'outlook.com',
        'hotmail' => 'hotmail.com', 'icloud' => 'icloud.com',
    ];

    /** Endings typed in place of ".com" (country domains like yahoo.com.ph are left alone). */
    private const WRONG_ENDINGS = ['net', 'org', 'co', 'con', 'cm', 'om', 'comm', 'cpm', 'vom', 'xom', 'cim', 'col', 'c0m'];

    /** Real mailbox domains that are a letter or two away from a provider's name. */
    private const REAL = ['mail.com', 'email.com', 'ymail.com', 'gmx.com', 'aol.com', 'live.com'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        [$local, $domain] = array_pad(explode('@', strtolower((string) $value), 2), 2, '');
        if ($local === '' || $domain === '') {
            return; // The email rule reports malformed addresses.
        }

        if ($suggestion = self::suggestion($domain)) {
            $fail("Did you mean {$local}@{$suggestion}? Check the email address.");

            return;
        }

        if (config('mail.verify_domains') && ! checkdnsrr($domain, 'MX')) {
            $fail("{$domain} can't receive email. Check for typos, like .net instead of .com.");
        }
    }

    /** The provider domain a mistyped one most likely meant, or null if it looks intended. */
    public static function suggestion(string $domain): ?string
    {
        if (in_array($domain, self::REAL, true) || in_array($domain, self::PROVIDERS, true)) {
            return null;
        }
        [$name, $ending] = array_pad(explode('.', $domain, 2), 2, '');

        // gmail.net, yahoo.co: the right provider with the wrong ending.
        if (isset(self::PROVIDERS[$name])) {
            return in_array($ending, self::WRONG_ENDINGS, true) ? self::PROVIDERS[$name] : null;
        }

        // gmial.com, gamil.com, hotmal.com: a misspelled provider with a .com-like ending.
        if ($ending === 'com' || in_array($ending, self::WRONG_ENDINGS, true)) {
            foreach (self::PROVIDERS as $provider => $real) {
                if (levenshtein($name, $provider) <= 2) {
                    return $real;
                }
            }
        }

        return null;
    }
}
