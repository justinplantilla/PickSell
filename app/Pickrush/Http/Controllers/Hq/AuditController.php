<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Hq\Alerts;
use Illuminate\Http\Request;

/** Read-only views for HQ: the alert list and the immutable audit log. */
class AuditController extends Controller
{
    public function alerts(Alerts $alerts)
    {
        return view('pickrush.hq.alerts', ['alerts' => $alerts->all()]);
    }

    public function index(Request $request)
    {
        $filters = [
            'action' => trim((string) $request->query('action', '')),
            'subject' => trim((string) $request->query('subject', '')),
            'subject_id' => $request->integer('subject_id') ?: null,
            'actor' => $request->integer('actor') ?: null,
            'from' => $request->date('from'),
            'to' => $request->date('to'),
        ];

        $logs = AuditLog::query()
            ->when($filters['action'] !== '', fn ($q) => $q->where('action', 'like', "%{$filters['action']}%"))
            ->when($filters['subject'] !== '', fn ($q) => $q->where('subject_type', $filters['subject']))
            ->when($filters['subject_id'], fn ($q) => $q->where('subject_id', $filters['subject_id']))
            ->when($filters['actor'], fn ($q) => $q->where('actor_type', 'staff')->where('actor_id', $filters['actor']))
            ->when($filters['from'], fn ($q) => $q->where('created_at', '>=', $filters['from']->startOfDay()))
            ->when($filters['to'], fn ($q) => $q->where('created_at', '<=', $filters['to']->endOfDay()))
            ->latest('id')->paginate(50)->withQueryString();

        return view('pickrush.hq.audit', [
            'logs' => $logs,
            'filters' => $filters,
            'actors' => Staff::whereIn('id', $logs->getCollection()->where('actor_type', 'staff')->pluck('actor_id'))->pluck('full_name', 'id'),
            'subjects' => AuditLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
