@extends('logistics.layout')
@vite('resources/css/views/logistics-account.css')
@section('title', 'My Account')
@section('content')
<div class="blade-inline-1"><h1 class="blade-inline-2">My Account</h1><p class="blade-inline-3">Your Logistics Sub-Admin profile and access details.</p></div>
<div class="card"><div class="card-header"><span class="card-title">Profile</span><span class="badge badge-approved">{{ ucfirst($user->status) }}</span></div><div class="card-body"><table><tr><td class="blade-inline-4">Name</td><td>{{ $user->full_name }}</td></tr><tr><td class="blade-inline-5">Email</td><td>{{ $user->email }}</td></tr><tr><td class="blade-inline-6">Contact</td><td>{{ $user->contact_no ?? '—' }}</td></tr><tr><td class="blade-inline-7">Role</td><td>Logistics Sub-Admin</td></tr><tr><td class="blade-inline-8">Branch</td><td>{{ $user->municipality ? $user->municipality.', '.$user->province : 'Network-wide access' }}</td></tr></table></div></div>
@endsection