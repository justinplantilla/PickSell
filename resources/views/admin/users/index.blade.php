@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-users.css'])
@endsection
@section('title', 'User Accounts')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">User accounts</span>
            <p class="oversight-description">Buyers, sellers, riders and logistics partners. Open an account to see its history and manage its status.</p>
        </div>
    </div>
    <form method="GET" class="oversight-filters oversight-note" role="search" aria-label="Filter user accounts">
        <input type="search" name="search" value="{{ $search }}" class="search-input" placeholder="Name, email, business or phone" aria-label="Search users">
        <select name="role" class="filter-select" aria-label="Role" data-submit-on-change>
            <option value="all" {{ $role === 'all' ? 'selected' : '' }}>All roles</option>
            @foreach(['buyer' => 'Buyers', 'seller' => 'Sellers', 'courier' => 'Riders', 'logistics' => 'Logistics partners'] as $value => $label)
                <option value="{{ $value }}" {{ $role === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="filter-select" aria-label="Status" data-submit-on-change>
            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All statuses</option>
            @foreach($statuses as $value)
                <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $value === 'approved' ? 'Active' : ucfirst($value) }}</option>
            @endforeach
        </select>
        <select name="joined" class="filter-select" aria-label="Joined" data-submit-on-change>
            <option value="any" {{ $joined === 'any' ? 'selected' : '' }}>Any join date</option>
            @foreach($joinedFilters as $value => $label)
                <option value="{{ $value }}" {{ $joined === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Search</button>
        @if($search !== '' || $role !== 'all' || $status !== 'all' || $joined !== 'any')
            <a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </form>

    @if($users->isEmpty())
        <div class="oversight-empty"><strong>No accounts found</strong>Try different filters.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Orders</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($users as $user)
                    @php
                        $orders = match ($user->role) {
                            'buyer' => $user->orders_as_buyer_count,
                            'seller' => $user->orders_as_seller_count,
                            'courier' => $user->orders_as_courier_count,
                            default => null,
                        };
                    @endphp
                    <tr>
                        <td><strong>{{ $user->full_name }}</strong><span class="oversight-meta">{{ $user->email }}@if($user->business_name) · {{ $user->business_name }}@endif</span></td>
                        <td><span class="badge badge-{{ $user->role }}">{{ $user->role === 'courier' ? 'Rider' : ucfirst($user->role) }}</span></td>
                        <td><span class="badge badge-{{ $user->status }}">{{ $user->status === 'approved' ? 'Active' : ucfirst($user->status) }}</span></td>
                        <td class="oversight-number">{{ $orders === null ? '—' : number_format($orders) }}</td>
                        <td><time datetime="{{ $user->created_at->toIso8601String() }}">{{ $user->created_at->format('M d, Y') }}</time></td>
                        <td><a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline btn-sm">View<span class="sr-only"> {{ $user->full_name }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="dashboard-pagination">{{ $users->links() }}</div>@endif
    @endif
</div>
@endsection
