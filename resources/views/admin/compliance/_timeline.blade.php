<ol class="compliance-timeline">
    @foreach($actions as $action)
        <li class="action-{{ $action->action }}">
            <span>
                <strong>{{ $action->label }}</strong>
                @if($action->action === \App\Models\ComplianceAction::SEVERITY_CHANGED && $action->metadata)
                    <span class="oversight-meta">{{ $action->metadata['from'] ?? '' }} → {{ $action->metadata['to'] ?? '' }}</span>
                @endif
                @if(($showCase ?? false) && $action->complianceCase)
                    <a href="{{ route('admin.compliance.cases.show', $action->complianceCase) }}" class="oversight-meta">case #{{ $action->compliance_case_id }}</a>
                @endif
            </span>
            <span class="oversight-meta">{{ $action->actor?->full_name ?? 'Admin' }} · <time datetime="{{ $action->created_at->toIso8601String() }}">{{ $action->created_at->format('M d, Y h:i A') }}</time></span>
            @if($action->reason)<p class="compliance-reason">{{ $action->reason }}</p>@endif
            @if($action->attachments)
                <ul class="compliance-evidence">
                    @foreach($action->attachments as $index => $file)
                        <li><a href="{{ route('admin.compliance.evidence', ['action' => $action, 'index' => $index]) }}" target="_blank" rel="noopener">📎 {{ $file['name'] }}<span class="sr-only"> (opens in new tab)</span></a> <span class="oversight-meta">{{ number_format(($file['size'] ?? 0) / 1024) }} KB</span></li>
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ol>
