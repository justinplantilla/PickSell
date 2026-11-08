<?php

namespace App\Pickrush\Mail;

use App\Pickrush\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells a logistics company a PickSell administrator approved, rejected, suspended or reinstated it. */
class CompanyApplicationDecidedMail extends Mailable
{
    /** @param  'approved'|'rejected'|'suspended'|'reinstated'  $decision */
    public function __construct(public Company $company, public string $decision, public ?string $reason = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->decision) {
            'approved' => "{$this->company->name} is approved on PickSell Logistics",
            'reinstated' => "{$this->company->name} is active again on PickSell Logistics",
            'suspended' => "{$this->company->name} is suspended on PickSell Logistics",
            default => "Update on your PickSell Logistics registration",
        });
    }

    public function content(): Content
    {
        return new Content(view: 'pickrush.emails.company-decision', with: [
            'decision' => $this->decision,
            'companyName' => $this->company->name,
            'name' => $this->company->owner?->first_name ?: $this->company->owner?->full_name,
            'reason' => $this->reason,
            'prefix' => $this->company->tracking_prefix,
            'loginUrl' => route('pickrush.login'),
        ]);
    }
}
