<div class="card report-filter-card">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.reports') }}" class="report-filters">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="form-group">
                <label class="form-label" for="report-from">From</label>
                <input id="report-from" type="date" name="from" class="form-control" value="{{ $filters['from'] }}" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="report-to">To</label>
                <input id="report-to" type="date" name="to" class="form-control" value="{{ $filters['to'] }}" min="{{ $filters['from'] }}" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="report-status">{{ $tab === 'financial' ? 'Transaction status' : 'Order status' }}</label>
                <select id="report-status" name="status" class="form-control">
                    @if($tab === 'financial')
                        @foreach(['all' => 'All statuses', 'pending' => 'Pending', 'posted' => 'Posted', 'voided' => 'Voided'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    @else
                        <option value="all" @selected($filters['status'] === 'all')>All statuses</option>
                        @foreach(\App\Services\Reports\OperationalReportService::statuses() as $value)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ \App\Services\Orders\OrderLifecycleService::label($value) }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="report-seller">Seller</label>
                <select id="report-seller" name="seller_id" class="form-control">
                    <option value="">All sellers</option>
                    @foreach($sellers as $seller)
                        <option value="{{ $seller->id }}" @selected((string) $filters['seller_id'] === (string) $seller->id)>{{ $seller->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-coral">Generate report</button>
            @can(\App\Auth\Permission::REPORTS_EXPORT)
                <a class="btn btn-outline" href="{{ route('admin.reports.export', array_merge($filters, ['type' => $tab])) }}">Export PDF</a>
            @endcan
        </form>
    </div>
</div>
