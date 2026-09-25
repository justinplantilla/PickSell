@extends('seller.layout')
@section('title', 'Chat / Messaging')

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
                @if($product->image)
                <img src="{{ Storage::url($product->image) }}" class="blade-inline-5">
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
        <div class="chat-empty">💬 Select a contact to start messaging</div>
        @endif
    </div>
</div>
@section('scripts')
@vite('resources/js/views/seller-chat.js')
@endsection
@endsection
