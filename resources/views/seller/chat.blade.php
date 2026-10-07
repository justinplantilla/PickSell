@extends('seller.layout')
@section('title', 'Chat / Messaging')
@section('body-class', 'seller-chat-screen')

@section('styles')
@vite('resources/css/views/seller-chat.css')
@endsection

@section('content')
<div class="chat-layout">
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <input type="text" class="chat-search" placeholder="Search..." oninput="filterUsers(this.value)">
        </div>
        <div class="chat-list" id="userList">
            @forelse($users as $user)
            <a href="/seller/chat?user={{ $user->id }}" class="chat-item {{ $activeUser && $activeUser->id === $user->id ? 'active' : '' }}" data-name="{{ strtolower($user->full_name) }}">
                <div class="chat-avatar {{ $user->role }}">{{ strtoupper(substr($user->first_name, 0, 1)) }}</div>
                <div class="chat-item-info">
                    <div class="chat-item-name">{{ $user->role === 'admin' ? 'PickSell Support' : $user->full_name }}</div>
                    <div class="chat-item-preview">{{ $user->role === 'admin' ? 'Customer support' : ucfirst($user->role) }}</div>
                </div>
                @if($user->unread > 0)
                <span class="unread-badge">{{ $user->unread }}</span>
                @endif
            </a>
            @empty
            <div class="blade-inline-1">No contacts yet.</div>
            @endforelse
        </div>
    </div>

    <div class="chat-main">
        @if($activeUser)
        <div class="chat-header">
            <div class="chat-avatar {{ $activeUser->role }}">{{ strtoupper(substr($activeUser->first_name, 0, 1)) }}</div>
            <div>
                <div class="blade-inline-2">{{ $activeUser->role === 'admin' ? 'PickSell Support' : $activeUser->full_name }}</div>
                <div class="blade-inline-3">{{ $activeUser->role === 'admin' ? 'Customer support' : ucfirst($activeUser->role).' · '.$activeUser->email }}</div>
            </div>
        </div>
        <div class="chat-messages" id="chatMessages">
            @if(isset($product) && $product)
            <div class="blade-inline-4">
                @if($product->primary_image)
                <img src="{{ Storage::url($product->primary_image) }}" class="blade-inline-5">
                @endif
                <div class="blade-inline-6">
                    <div class="blade-inline-7">Inquiring about</div>
                    <div class="blade-inline-8">{{ $product->name }}</div>
                    <div class="blade-inline-9">₱{{ number_format($product->effective_price, 2) }}</div>
                </div>
            </div>
            @endif
            @forelse($messages as $msg)
            <div class="msg {{ $msg->sender_id === auth()->id() ? 'sent' : 'received' }}">
                <div class="msg-bubble">{{ $msg->body }}</div>
                <div class="msg-time">{{ $msg->created_at->format('M d, h:i A') }}</div>
            </div>
            @empty
            <div class="blade-inline-10">No messages yet. Start the conversation!</div>
            @endforelse
        </div>
        <div class="chat-input-area">
            <form method="POST" action="/seller/chat/send" class="blade-inline-11">
                @csrf
                <input type="hidden" name="receiver_id" value="{{ $activeUser->id }}">
                <input type="text" name="body" class="chat-input" placeholder="Type a message..." required autocomplete="off">
                <button type="submit" class="btn btn-coral">Send</button>
            </form>
        </div>
        @else
        <div class="chat-empty">
            <svg class="seller-chat-empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true">
                <path d="M10 12h44v31H29L16 54V43h-6z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
                <path d="M21 25h22M21 33h15" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <strong>PickSell Messages</strong>
            <span>Select a buyer or PickSell Support to view and send messages.</span>
        </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-chat.js')
@endsection
