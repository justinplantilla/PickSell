<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return view('admin.audit.index', [
            'logs' => $this->query($filters)->paginate(30)->withQueryString(),
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /** CSV export of the filtered log. The export itself is audited. */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize(Permission::AUDIT_EXPORT);
        $filters = $this->filters($request);
        $audit->record('audit.exported', null, [], ['filters' => array_filter($filters)], Permission::AUDIT_EXPORT);

        $query = $this->query($filters);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'actor_id', 'actor', 'actor_role', 'action', 'permission', 'subject_type', 'subject_id', 'changes', 'metadata', 'ip_address']);
            $query->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->id,
                        $log->created_at?->toIso8601String(),
                        $log->actor_id,
                        $log->actor?->full_name,
                        $log->actor_role,
                        $log->action,
                        $log->permission,
                        $log->subject_type,
                        $log->subject_id,
                        $log->changes ? json_encode($log->changes) : '',
                        $log->metadata ? json_encode($log->metadata) : '',
                        $log->ip_address,
                    ]);
                }
            });
            fclose($out);
        }, 'picksell-audit-log-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        $date = fn ($value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? $value : null;

        return [
            'action' => is_string($request->query('action')) ? $request->query('action') : null,
            'actor' => is_numeric($request->query('actor')) ? (int) $request->query('actor') : null,
            'from' => $date($request->query('from')),
            'to' => $date($request->query('to')),
            'denied' => $request->boolean('denied'),
        ];
    }

    private function query(array $filters)
    {
        return AuditLog::with('actor')
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action))
            ->when($filters['actor'], fn ($q, $actor) => $q->where('actor_id', $actor))
            ->when($filters['from'], fn ($q, $from) => $q->where('created_at', '>=', $from . ' 00:00:00'))
            ->when($filters['to'], fn ($q, $to) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->when($filters['denied'], fn ($q) => $q->where('action', 'authorization.denied'))
            ->orderByDesc('id');
    }
}
