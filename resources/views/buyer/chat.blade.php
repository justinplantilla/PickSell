@extends('buyer.layout')
@section('title', 'Messages')

@section('styles')
@vite('resources/css/views/buyer-chat.css')
@endsection

@section('content')
<div class="chat-layout">
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <div class="chat-sidebar-title"><h1>Messages</h1><span>{{ $contacts->count() }}</span></div>
            <p class="chat-sidebar-subtitle">Keep track of your seller conversations.</p>
            <input type="text" class="chat-search" placeholder="Search conversations..." oninput="filterUsers(this.value)">
        </div>
        <div class="chat-list" id="userList">
            @forelse($contacts as $user)
            <a href="/buyer/chat?user={{ $user->id }}" class="chat-item {{ $activeUser && $activeUser->id === $user->id ? 'active' : '' }}" data-name="{{ strtolower($user->full_name) }}">
                <div class="chat-avatar {{ $user->role }}">{{ strtoupper(substr($user->first_name, 0, 1)) }}</div>
                <div class="chat-item-info">
                    <div class="chat-item-name">{{ $user->full_name }}</div>
                    <div class="chat-item-preview">{{ $user->role === 'admin' ? 'PickSell Support' : ($user->business_name ?? 'Seller') }}</div>
                </div>
                @if($user->unread > 0)
                <span class="unread-badge">{{ $user->unread }}</span>
                @endif
            </a>
            @empty
            <div class="blade-inline-1">
                <svg class="chat-empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4Zm3 3h10m-10 3h7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <strong>No conversations yet</strong>
                <small>Start a conversation from any product page.</small>
                <a href="/buyer/shop" class="chat-empty-link">Browse products</a>
            </div>
            @endforelse
        </div>
    </div>

    <div class="chat-main">
        @if($activeUser)
        <div class="chat-header">
            <div class="chat-avatar {{ $activeUser->role }}">{{ strtoupper(substr($activeUser->first_name, 0, 1)) }}</div>
            <div class="blade-inline-2">
                <div class="blade-inline-3">{{ $activeUser->full_name }}</div>
                <div class="blade-inline-4">{{ $activeUser->role === 'admin' ? 'PickSell Support' : ($activeUser->business_name ?? 'Seller') }}</div>
                <div class="chat-presence"><span></span>{{ $activeUser->role === 'admin' ? 'Support team' : 'Verified seller' }}</div>
            </div>
        </div>
        @if(isset($product) && $product)
        <div class="chat-product-context">
            @if($product->image)
            <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}">
            @endif
            <div class="chat-product-copy">
                <span class="chat-product-kicker">Product inquiry</span>
                <strong>{{ $product->name }}</strong>
                <span class="chat-product-price">₱{{ number_format($product->effective_price, 2) }}</span>
            </div>
            <a href="/buyer/product/{{ $product->id }}" class="chat-product-link">View details</a>
        </div>
        @endif
        <div class="chat-messages" id="chatMessages">
            @forelse($messages as $msg)
            <div class="msg {{ $msg->sender_id === auth()->id() ? 'sent' : 'received' }}">
                @if($msg->product)
                <div class="msg-product-tag">Re: {{ $msg->product->name }}</div>
                @endif
                <div class="msg-bubble">{{ $msg->body }}</div>
                <div class="msg-time">{{ $msg->created_at->format('M d, h:i A') }}</div>
            </div>
            @empty
            <div class="blade-inline-16">No messages yet. Start the conversation!</div>
            @endforelse
        </div>
        <div class="chat-input-area">
            @if(isset($product) && $product)
            <div class="product-context">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="#b45309" viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.06 15.94 0 13.36 0c-1.3 0-2.48.52-3.36 1.36C9.12.52 7.94 0 6.64 0 4.06 0 2 2.06 2 4.64c0 .48.11.92.18 1.36H0v14c0 1.1.9 2 2 2h20c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
                Asking about: <strong>{{ $product->name }}</strong>
            </div>
            @endif
            <form method="POST" action="/buyer/chat/send" class="blade-inline-17">
                @csrf
                <input type="hidden" name="receiver_id" value="{{ $activeUser->id }}">
                @if(isset($product) && $product)
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                @endif
                <input type="text" name="body" class="chat-input" placeholder="Type a message..." required autocomplete="off">
                <button type="submit" class="btn btn-coral chat-send-btn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 9-18 9 3-9Zm0 0 3 9h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Send</span></button>
            </form>
        </div>
        @else
        <div class="chat-empty">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#ddd" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
            <strong>Select a conversation</strong>
            <span>Choose a seller from the list or open a product and tap Chat Seller.</span>
            <a href="/buyer/shop" class="chat-empty-link">Browse products</a>
        </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/buyer-chat.js')
@endsection
