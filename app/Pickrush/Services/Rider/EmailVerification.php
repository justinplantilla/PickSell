<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Mail\RiderEmailCodeMail;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderEmailCode;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Proves an applicant owns their email before they can apply: a 6-digit code by email (free, no
 * SMS), then a short-lived token the app sends with the application. Each token works once.
 */
class EmailVerification
{
    public const CODE_MINUTES = 10;

    public const TOKEN_MINUTES = 60;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public function send(string $email): void
    {
        $email = self::normalize($email);
        if (Rider::where('email', $email)->exists()) {
            throw new OperationRefused('This email is already registered. Log in instead.');
        }
        $last = RiderEmailCode::where('email', $email)->latest('id')->first();
        if ($last && $last->created_at->diffInSeconds(now()) < self::RESEND_SECONDS) {
            throw new OperationRefused('A code was just sent. Wait a minute before asking for another.');
        }

        $code = (string) random_int(100000, 999999);
        RiderEmailCode::where('email', $email)->whereNull('verified_at')->delete();
        RiderEmailCode::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_MINUTES),
        ]);

        Mail::to($email)->send(new RiderEmailCodeMail($code, self::CODE_MINUTES));
    }

    /** Returns the verification token for a correct code. */
    public function verify(string $email, string $code): string
    {
        $record = RiderEmailCode::where('email', self::normalize($email))->whereNull('verified_at')->latest('id')->first();
        if (! $record || $record->expires_at->isPast()) {
            throw new OperationRefused('The code has expired. Ask for a new one.');
        }
        if ($record->attempts >= self::MAX_ATTEMPTS) {
            throw new OperationRefused('Too many wrong codes. Ask for a new one.');
        }
        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            throw new OperationRefused('Incorrect code. Please try again.');
        }

        $token = Str::random(64);
        $record->update(['verified_at' => now(), 'token_hash' => hash('sha256', $token)]);

        return $token;
    }

    /** Uses up the token. False when it is wrong, for another email, or older than an hour. */
    public function consume(string $email, string $token): bool
    {
        $record = RiderEmailCode::where('token_hash', hash('sha256', $token))->first();
        if (! $record || $record->email !== self::normalize($email) || $record->verified_at->addMinutes(self::TOKEN_MINUTES)->isPast()) {
            return false;
        }
        $record->delete();

        return true;
    }

    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
