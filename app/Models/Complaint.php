<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    public const RESOLUTION_TYPES = [
        'no_financial_action',
        'return',
        'refund',
    ];

    protected $fillable = [
        'filed_by', 'against_user_id', 'subject', 'details',
        'evidence_path', 'order_id', 'status', 'admin_notes',
        'resolution_type', 'resolution_notes', 'resolved_at',
    ];

    public function filer()   { return $this->belongsTo(User::class, 'filed_by'); }
    public function against() { return $this->belongsTo(User::class, 'against_user_id'); }
    public function order()   { return $this->belongsTo(Order::class); }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(function (Complaint $complaint): void {
            app(\App\Services\AdminNotificationService::class)->notifyAdmins(
                'complaint.opened',
                'Complaint opened',
                "A complaint was filed: {$complaint->subject}.",
                route('admin.complaints.show', ['complaint' => $complaint], false),
            );
        });
    }
}
