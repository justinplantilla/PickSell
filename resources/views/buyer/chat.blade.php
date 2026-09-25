@extends('buyer.layout')
@section('title', 'Messages')

@section('styles')
@vite('resources/css/views/buyer-chat.css')
@endsection

@section('content')
<div class="chat-layout">
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <input type="text" class="chat-search" placeholder="Search..." oninput="filterUsers(this.value)">
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
                No conversations yet.<br>
                <small>Chat a seller from a product page.</small>
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
            </div>
            @if(isset($product) && $product)
            <div class="blade-inline-5">
                @if($product->image)
                <img src="{{ Storage::url($product->image) }}" class="blade-inline-6">
                @endif
                <div>
                    <div class="blade-inline-7">{{ $product->name }}</div>
                    <div class="blade-inline-8">₱{{ number_format($product->effective_price, 2) }}</div>
                </div>
            </div>
            @endif
        </div>
        <div class="chat-messages" id="chatMessages">
            @if(isset($product) && $product)
            <div class="blade-inline-9">
                @if($product->image)
                <img src="{{ Storage::url($product->image) }}" class="blade-inline-10">
                @endif
                <div class="blade-inline-11">
                    <div class="blade-inline-12">Inquiring about</div>
                    <div class="blade-inline-13">{{ $product->name }}</div>
                    <div class="blade-inline-14">₱{{ number_format($product->effective_price, 2) }}</div>
                </div>
                <a href="/buyer/product/{{ $product->id }}" class="blade-inline-15">View Product →</a>
            </div>
            @endif
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
                <button type="submit" class="btn btn-coral">Send</button>
            </form>
        </div>
        @else
        <div class="chat-empty">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#ddd" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
            <span>Select a conversation or chat a seller from a product page</span>
        </div>
        @endif
    </div>
</div>
@section('scripts')
@vite('resources/js/views/buyer-chat.js')
@endsection
@endsection
