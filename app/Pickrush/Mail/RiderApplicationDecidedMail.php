<?php

namespace App\Pickrush\Mail;

use App\Pickrush\Models\Rider;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells an applicant the station hub manager approved or rejected their rider application. */
class RiderApplicationDecidedMail extends Mailable
{
    public function __construct(public Rider $rider) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rider->isActive()
            ? 'Your Pickrush rider account is approved'
            : 'Update on your Pickrush rider application');
    }

    public function content(): Content
    {
        return new Content(view: 'pickrush.emails.rider-decision', with: [
            'approved' => $this->rider->isActive(),
            'name' => $this->rider->first_name ?: $this->rider->full_name,
            'station' => $this->rider->station?->name,
            'reason' => $this->rider->status_reason,
        ]);
    }
}
