<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $newStatus) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your PickSell Account Status Has Changed');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-status-changed');
    }
}
