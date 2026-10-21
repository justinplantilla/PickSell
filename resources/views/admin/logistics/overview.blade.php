@extends('admin.layout')
@section('title', 'Logistics Companies')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<p class="oversight-description">Logistics companies manage their own branches and hub operations from the Logistics portal.</p>

<section class="card">
    <div class="card-header"><span class="card-title">Logistics companies ({{ number_format($companies->count()) }})</span></div>
    @if($companies->isEmpty())
        <div class="oversight-empty"><strong>No logistics companies yet</strong>Approved companies can register and manage their delivery branches in the Logistics portal.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Company</th><th>Contact person</th><th>Email</th><th>Branches</th><th>Account status</th><th></th></tr></thead>
                <tbody>
                @foreach($companies as $company)
                    <tr>
                        <td><strong>{{ $company->business_name ?? 'Company name not provided' }}</strong></td>
                        <td>{{ $company->full_name }}</td>
                        <td>{{ $company->email }}</td>
                        <td class="oversight-number">{{ number_format($company->branches_count) }}</td>
                        <td><span class="badge badge-{{ $company->status }}">{{ ucfirst($company->status) }}</span></td>
                        <td>
                            @if($company->status === 'pending')
                                <a href="{{ route('admin.registrations.show', $company) }}" class="btn btn-outline btn-sm">Review application</a>
                            @else
                                <a href="{{ route('admin.users.show', $company) }}" class="btn btn-outline btn-sm">View account</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="card">
    <div class="card-header">
        <span class="card-title">Open logistics exceptions ({{ number_format($openExceptionCount) }})</span>
        <a class="btn btn-outline" href="{{ route('admin.disputes') }}">Return / refund disputes</a>
    </div>
    @if($openExceptions->isEmpty())
        <div class="oversight-empty"><strong>No open logistics exceptions</strong>Parcel exceptions are shown here alongside any related buyer return or refund request.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Parcel</th><th>Branch</th><th>Exception</th><th>Buyer return / refund</th><th>Opened</th></tr></thead>
                <tbody>
                @foreach($openExceptions as $exception)
                    <tr>
                        <td><strong>{{ $exception->order->order_number }}</strong><span class="oversight-meta">{{ $exception->order->tracking_status ?? ucfirst(str_replace('_', ' ', $exception->order->status)) }}</span></td>
                        <td>{{ $exception->order->destinationBranch?->name ?? $exception->order->logistics?->business_name ?? 'Branch not assigned' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $exception->type)) }}<span class="oversight-meta">{{ \Illuminate\Support\Str::limit($exception->description, 90) }}</span></td>
                        <td>
                            @if($exception->order->returnRequest)
                                <a href="{{ route('admin.returns.show', $exception->order->returnRequest) }}">
                                    {{ ucfirst(str_replace('_', ' ', $exception->order->returnRequest->status)) }}
                                    @if($exception->order->returnRequest->dispute_status === 'open') · dispute open @endif
                                </a>
                            @else
                                <span class="oversight-meta">No buyer return request</span>
                            @endif
                        </td>
                        <td><time datetime="{{ $exception->created_at->toIso8601String() }}">{{ $exception->created_at->diffForHumans() }}</time></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
