<?php

namespace App\Models;

use App\Pickrush\Models\Staff;
use Illuminate\Database\Eloquent\Model;

/** One step in a logistics issue's history: opened, a note, escalation, the company's reply, resolution. */
class LogisticsIssueEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'opened' => 'Opened',
        'note' => 'Note',
        'escalated' => 'Escalated to the company',
        'company_response' => 'Company replied',
        'company_resolved' => 'Company resolved its exception',
        'resolved' => 'Resolved',
        'claim_charged' => 'Claim charged',
        'claim_waived' => 'Claim waived',
    ];

    protected $guarded = ['id'];

    public function issue()
    {
        return $this->belongsTo(LogisticsIssue::class, 'logistics_issue_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    public function actorName(): string
    {
        return $this->user?->full_name ?? $this->staff?->full_name ?? 'System';
    }
}
