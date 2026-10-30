<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Mail\RiderEmailCodeMail;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderEmailCode;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * "Forgot password?" in the rider app: a 6-digit code goes only to the email registered on the
 * account (free, no SMS), and the app shows that address masked. The answer is the same whether
 * or not the email is registered, so the form cannot be used to find riders' emails.
 */
class PasswordReset
{
    /** Sends the code when the email belongs to a rider who can sign in. Returns the masked address. */
    public function send(string $email): string
    {
        $email = EmailVerification::normalize($email);
        $rider = Rider::where('email', $email)->first();

        if ($rider && $rider->password && $rider->status !== 'deactivated') {
            $last = RiderEmailCode::where('email', $email)->latest('id')->first();
            if ($last && $last->created_at->diffInSeconds(now()) < EmailVerification::RESEND_SECONDS) {
                throw new OperationRefused('A code was just sent. Wait a minute before asking for another.');
            }
            $code = (string) random_int(100000, 999999);
            RiderEmailCode::where('email', $email)->whereNull('verified_at')->delete();
            RiderEmailCode::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(EmailVerification::CODE_MINUTES),
            ]);
            Mail::to($email)->send(new RiderEmailCodeMail($code, EmailVerification::CODE_MINUTES, 'reset'));
        }

        return self::mask($email);
    }

    /** A correct code sets the new password and signs the account out everywhere. */
    public function reset(string $email, string $code, string $password): void
    {
        $email = EmailVerification::normalize($email);
        $rider = Rider::where('email', $email)->first();
        $record = RiderEmailCode::where('email', $email)->whereNull('verified_at')->latest('id')->first();

        if (! $rider || ! $record || $record->expires_at->isPast()) {
            throw new OperationRefused('The code has expired. Ask for a new one.');
        }
        if ($record->attempts >= EmailVerification::MAX_ATTEMPTS) {
            throw new OperationRefused('Too many wrong codes. Ask for a new one.');
        }
        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            throw new OperationRefused('Incorrect code. Please try again.');
        }

        DB::transaction(function () use ($rider, $record, $password) {
            $rider->update(['password' => $password]);
            $rider->tokens()->delete();
            $record->delete();
        });
    }

    /** "christian123@gmail.com" → "ch•••••••23@gmail.com" */
    public static function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $shown = Str::length($name) <= 4 ? Str::substr($name, 0, 1) : Str::substr($name, 0, 2);
        $tail = Str::length($name) <= 4 ? '' : Str::substr($name, -2);
        $hidden = max(3, Str::length($name) - Str::length($shown) - Str::length($tail));

        return $shown.str_repeat('•', $hidden).$tail.($domain !== '' ? "@{$domain}" : '');
    }
}
