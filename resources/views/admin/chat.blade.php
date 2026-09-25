@extends('admin.layout')
@section('title', 'Chat / Messaging')

@section('styles')
@vite('resources/css/views/admin-chat.css')
@endsection

@section('content')
<div class="chat-layout">
    <!-- User List -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <input type="text" class="chat-search" placeholder="Search users..." oninput="filterUsers(this.value)">
        </div>
        <div class="chat-list" id="userList">
            @forelse($users as $user)
            <a href="/admin/chat?user={{ $user->id }}" class="chat-item {{ $activeUser && $activeUser->id === $user->id ? 'active' : '' }}" data-name="{{ strtolower($user->full_name) }}">
                <div class="chat-avatar {{ $user->role }}">{{ strtoupper(substr($user->first_name, 0, 1)) }}</div>
                <div class="chat-item-info">
                    <div class="chat-item-name">{{ $user->full_name }}</div>
                    <div class="chat-item-preview">{{ ucfirst($user->role) }}</div>
                </div>
                @if($user->unread > 0)
                <span class="unread-badge">{{ $user->unread }}</span>
                @endif
            </a>
            @empty
            <div class="blade-inline-1">No approved users yet.</div>
            @endforelse
        </div>
    </div>

    <!-- Chat Area -->
    <div class="chat-main">
        @if($activeUser)
        <div class="chat-header">
            <div class="chat-avatar {{ $activeUser->role }}">{{ strtoupper(substr($activeUser->first_name, 0, 1)) }}</div>
            <div>
                <div class="blade-inline-2">{{ $activeUser->full_name }}</div>
                <div class="blade-inline-3">{{ ucfirst($activeUser->role) }} · {{ $activeUser->email }}</div>
            </div>
        </div>
        <div class="chat-messages" id="chatMessages">
            @forelse($messages as $msg)
            <div class="msg {{ $msg->sender_id === auth()->id() ? 'sent' : 'received' }}">
                <div class="msg-bubble">{{ $msg->body }}</div>
                <div class="msg-time">{{ $msg->created_at->format('M d, h:i A') }}</div>
            </div>
            @empty
            <div class="blade-inline-4">No messages yet. Start the conversation!</div>
            @endforelse
        </div>
        <div class="chat-input-area">
            <form method="POST" action="/admin/chat/send" class="blade-inline-5" id="msgForm">
                @csrf
                <input type="hidden" name="receiver_id" value="{{ $activeUser->id }}">
                <input type="text" name="body" class="chat-input" placeholder="Type a message..." required autocomplete="off">
                <button type="submit" class="btn btn-coral">Send</button>
            </form>
        </div>
        @else
        <div class="chat-empty">💬 Select a user to start messaging</div>
        @endif
    </div>
</div>

@section('scripts')
@vite('resources/js/views/admin-chat.js')
@endsection
@endsection
