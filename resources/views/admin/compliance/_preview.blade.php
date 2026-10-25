{{-- Seller compliance summary loaded into the side panel on the compliance list. --}}
<div class="list-preview compliance-preview">
    <p class="list-preview-kicker">Seller compliance</p>
    <h2 id="list-drawer-title">{{ $seller->business_name ?? $seller->full_name }}</h2>
    <div class="list-preview-status">
        @include('admin.compliance._risk', ['risk' => $risk])
        <span class="badge badge-{{ $seller->status }}">{{ $seller->status === 'approved' ? 'Active' : ucfirst($seller->status) }}</span>
        <span class="oversight-meta">Score {{ $risk['score'] }}</span>
    </div>

    <h3 class="list-preview-heading">Risk breakdown</h3>
    @if($risk['factors'])
        <ul class="risk-factors">
            @foreach($risk['factors'] as $factor => $points)
                <li>{{ $factor }}: <strong>{{ $points }}</strong></li>
            @endforeach
        </ul>
        <p class="oversight-meta">Off-category listings, Admin-archived products and open complaints each add at most {{ \App\Services\Admin\ComplianceService::FACTOR_CAP }} points.</p>
    @else
        <p class="oversight-meta">No compliance flags.</p>
    @endif

    <dl class="compliance-fields">
        <div><dt>Owner</dt><dd>{{ $seller->full_name }}</dd></div>
        <div><dt>Email</dt><dd>{{ $seller->email }}</dd></div>
        <div><dt>Registered category</dt><dd>{{ $seller->line_of_business ?? '—' }}</dd></div>
        <div><dt>Off-category listings</dt><dd>{{ number_format($seller->mismatched_products_count) }}</dd></div>
        <div><dt>Open cases</dt><dd>{{ number_format($seller->open_cases_count) }}</dd></div>
        <div><dt>Warnings (90 days)</dt><dd>{{ number_format($seller->recent_warnings_count) }}</dd></div>
        <div><dt>Archived by Admin</dt><dd>{{ number_format($seller->held_products_count) }}</dd></div>
        <div><dt>Open complaints</dt><dd>{{ number_format($seller->open_complaints_count) }}</dd></div>
    </dl>

    @if($mismatched->isNotEmpty())
        <h3 class="list-preview-heading">Off-category listings</h3>
        <ul class="compliance-preview-list">
            @foreach($mismatched as $product)
                <li><span>{{ $product->name }}</span><span class="oversight-meta">{{ $product->category }}</span></li>
            @endforeach
        </ul>
        @if($seller->mismatched_products_count > $mismatched->count())<p class="oversight-meta">+{{ $seller->mismatched_products_count - $mismatched->count() }} more on the full review.</p>@endif
    @endif

    @if($openCases->isNotEmpty())
        <h3 class="list-preview-heading">Open cases</h3>
        <ul class="compliance-preview-list">
            @foreach($openCases as $case)
                <li>
                    <a href="{{ route('admin.compliance.cases.show', $case) }}">#{{ $case->id }} · {{ \App\Models\ComplianceCase::TYPES[$case->type] ?? ucfirst(str_replace('_', ' ', $case->type)) }}</a>
                    <span class="severity-badge severity-{{ $case->severity }}">{{ ucfirst($case->severity) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if($warnings->isNotEmpty())
        <h3 class="list-preview-heading">Recent warnings</h3>
        <ol class="compliance-timeline compliance-preview-timeline">
            @foreach($warnings as $warning)
                <li class="action-warning">
                    <span class="oversight-meta">{{ $warning->created_at->format('M d, Y') }} · {{ $warning->actor?->full_name ?? 'Admin' }}</span>
                    <p class="compliance-reason">{{ $warning->reason }}</p>
                </li>
            @endforeach
        </ol>
    @endif

    <a href="{{ route('admin.compliance.seller', $seller) }}" class="btn btn-coral list-preview-open">Open full review</a>
</div>
