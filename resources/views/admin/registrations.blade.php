@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-registrations.css'])
@endsection
@section('title', 'Registrations')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Registration queue</span>
            <p class="oversight-description">{{ $status === 'pending' ? 'Oldest applications first.' : 'Newest first.' }} Courier applications are reviewed by Logistics.</p>
        </div>
    </div>
    <form method="GET" class="oversight-filters oversight-note registration-filters" role="search" aria-label="Filter registrations">
        <input type="search" name="search" value="{{ $search }}" class="search-input" placeholder="Name, email or business" aria-label="Search applicants">
        <select name="role" class="filter-select" aria-label="Role" data-submit-on-change>
            <option value="all" {{ $role === 'all' ? 'selected' : '' }}>All roles</option>
            @foreach(['buyer' => 'Buyer', 'seller' => 'Seller', 'logistics' => 'Logistics provider', 'courier' => 'Courier'] as $value => $label)
                <option value="{{ $value }}" {{ $role === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="filter-select" aria-label="Status" data-submit-on-change>
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'disapproved' => 'Disapproved', 'all' => 'All statuses'] as $value => $label)
                <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="date" class="filter-select" aria-label="Submitted" data-submit-on-change>
            <option value="any" {{ $date === 'any' ? 'selected' : '' }}>Any date</option>
            @foreach($dateFilters as $value => $label)
                <option value="{{ $value }}" {{ $date === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Search</button>
        @if($search !== '' || $role !== 'all' || $status !== 'pending' || $date !== 'any')
            <a href="{{ route('admin.registrations') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </form>

    @if($users->isEmpty())
        <div class="oversight-empty"><strong>No applications found</strong>{{ $status === 'pending' ? 'The queue is clear.' : 'Try different filters.' }}</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Applicant</th><th>Role</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->full_name }}</strong>
                            <span class="oversight-meta">{{ $user->email }}@if($user->business_name) · {{ $user->business_name }}@endif</span>
                        </td>
                        <td><span class="badge badge-{{ $user->role }}">{{ $user->role === 'logistics' ? 'Logistics' : ucfirst($user->role) }}</span></td>
                        <td>
                            <time datetime="{{ $user->created_at->toIso8601String() }}">{{ $user->created_at->format('M d, Y') }}</time>
                            <span class="oversight-meta">{{ $user->created_at->diffForHumans() }}</span>
                        </td>
                        <td>
                            <span class="badge badge-{{ $user->status }}">{{ ucfirst($user->status) }}</span>
                            @if($review = $user->latestRegistrationReview)
                                @if($user->status === 'pending')
                                    <span class="oversight-meta">Re-applied · previously {{ $review->decision }} {{ $review->reviewed_at->format('M d') }}</span>
                                @else
                                    <span class="oversight-meta">by {{ $review->reviewer?->full_name ?? 'Admin' }} · {{ $review->reviewed_at->format('M d') }}</span>
                                @endif
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.registrations.show', $user) }}" class="btn btn-sm {{ $user->status === 'pending' && $user->role !== 'courier' ? 'btn-coral' : 'btn-outline' }}">
                                {{ $user->status === 'pending' && $user->role !== 'courier' ? 'Review' : 'View' }}<span class="sr-only"> {{ $user->full_name }}</span>
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="dashboard-pagination">{{ $users->links() }}</div>@endif
    @endif
</div>
@endsection
