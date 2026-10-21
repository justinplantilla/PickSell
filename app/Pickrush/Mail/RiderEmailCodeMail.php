<?php

namespace App\Pickrush\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The verification code for someone applying as a rider in the rider app. */
class RiderEmailCodeMail extends Mailable
{
    public function __construct(public string $code, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your Pickrush rider verification code");
    }

    public function content(): Content
    {
        return new Content(view: 'pickrush.emails.rider-code');
    }
}
