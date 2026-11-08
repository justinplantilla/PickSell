<?php

namespace App\Pickrush\Mail;

use App\Pickrush\Models\Company;
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
            ? "Your {$this->companyName()} rider account is approved"
            : "Update on your {$this->companyName()} rider application");
    }

    public function content(): Content
    {
        return new Content(view: 'pickrush.emails.rider-decision', with: [
            'approved' => $this->rider->isActive(),
            'name' => $this->rider->first_name ?: $this->rider->full_name,
            'station' => $this->rider->station?->name,
            'reason' => $this->rider->status_reason,
            'companyName' => $this->companyName(),
        ]);
    }

    private function companyName(): string
    {
        return Company::find($this->rider->company_id)?->name ?? config('pickrush.portal_name');
    }
}
