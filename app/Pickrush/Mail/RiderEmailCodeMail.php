<?php

namespace App\Pickrush\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A 6-digit code from the rider app: verifying an applicant's email, or resetting a password. */
class RiderEmailCodeMail extends Mailable
{
    /** @param  string  $purpose  verify (applying) or reset (forgot password) */
    public function __construct(public string $code, public int $minutes, public string $purpose = 'verify') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'reset'
            ? "{$this->code} is your rider app password reset code"
            : "{$this->code} is your rider verification code");
    }

    public function content(): Content
    {
        return new Content(view: 'pickrush.emails.rider-code');
    }
}
