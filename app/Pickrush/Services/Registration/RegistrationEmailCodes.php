<?php

namespace App\Pickrush\Services\Registration;

use App\Services\EmailVerificationService;
use Illuminate\Support\Facades\Cache;

/**
 * The email code on the company registration form. Codes come from the marketplace's
 * EmailVerificationService (6 digits, hashed, 5 minutes, single use, 5 tries, a new code cancels
 * the old one, 60 s between sends). On top of that, 5 wrong tries lock the address for a while,
 * so a new code cannot be requested straight away to keep guessing.
 */
class RegistrationEmailCodes
{
    public function __construct(private EmailVerificationService $codes) {}

    /**
     * @return array{status: 'sent'|'wait'|'locked'|'failed', wait?: int, expires_in?: int}
     */
    public function send(string $email): array
    {
        if ($locked = $this->lockedFor($email)) {
            return ['status' => 'locked', 'wait' => $locked];
        }
        if (($wait = $this->codes->resendWaitSeconds($email)) > 0) {
            return ['status' => 'wait', 'wait' => $wait];
        }
        if (! $this->codes->send($email)) {
            return ['status' => 'failed'];
        }

        return [
            'status' => 'sent',
            'wait' => EmailVerificationService::RESEND_COOLDOWN_SECONDS,
            'expires_in' => EmailVerificationService::CODE_TTL_MINUTES * 60,
        ];
    }

    /**
     * @return array{status: 'verified'|'invalid'|'expired'|'locked'|'missing', attempts_left?: int, wait?: int}
     */
    public function verify(string $email, string $code): array
    {
        if ($locked = $this->lockedFor($email)) {
            return ['status' => 'locked', 'wait' => $locked];
        }

        $result = $this->codes->check($email, $code);
        if ($result === 'locked') {
            $minutes = (int) config('pickrush.registration.lockout_minutes', 15);
            Cache::put($this->lockKey($email), now()->addMinutes($minutes)->getTimestamp(), now()->addMinutes($minutes));

            return ['status' => 'locked', 'wait' => $minutes * 60];
        }

        return $result === 'invalid'
            ? ['status' => 'invalid', 'attempts_left' => $this->codes->attemptsLeft($email)]
            : ['status' => $result];
    }

    /** Seconds left on a lock after too many wrong codes; 0 when not locked. */
    public function lockedFor(string $email): int
    {
        $until = Cache::get($this->lockKey($email));

        return $until ? max(0, $until - now()->getTimestamp()) : 0;
    }

    private function lockKey(string $email): string
    {
        return 'logistics-otp-lock:'.sha1(strtolower(trim($email)));
    }
}
