@extends('courier.layout')
@section('title', 'Chat')
@section('content')
<h1 style="font-size:1.45rem;margin:0 0 1rem;">Chat / Messaging</h1>
<div class="card" style="display:grid;grid-template-columns:240px 1fr;min-height:480px;">
    <div style="border-right:1px solid #eee;padding:1rem;">
        <strong>Contacts</strong>
        <div style="margin-top:1rem;display:flex;flex-direction:column;gap:0.5rem;">@forelse($contacts as $contact)<a href="{{ route('courier.chat', ['user' => $contact->id]) }}" style="padding:0.55rem;border-radius:6px;background:{{ optional($activeUser)->id === $contact->id ? '#fff3f0' : '#fafafa' }};">{{ $contact->full_name }}<small style="display:block;color:#888;">{{ ucfirst($contact->role) }}</small></a>@empty<span style="font-size:0.82rem;color:#888;">No conversations yet.</span>@endforelse</div>
    </div>
    <div style="padding:1rem;display:flex;flex-direction:column;">
        @if($activeUser)<div style="font-weight:700;padding-bottom:0.8rem;border-bottom:1px solid #eee;">{{ $activeUser->full_name }}</div><div style="flex:1;padding:1rem 0;">@forelse($messages as $message)<div style="margin-bottom:0.6rem;text-align:{{ $message->sender_id === auth()->id() ? 'right' : 'left' }};"><span style="display:inline-block;padding:0.5rem 0.7rem;border-radius:8px;background:{{ $message->sender_id === auth()->id() ? '#fff3f0' : '#f4f4f4' }};">{{ $message->body }}</span></div>@empty<span style="color:#888;font-size:0.85rem;">No messages yet.</span>@endforelse</div><form method="POST" action="{{ route('courier.chat.send') }}" style="display:flex;gap:0.5rem;">@csrf<input type="hidden" name="receiver_id" value="{{ $activeUser->id }}"><input class="form-control" style="flex:1;" name="message" placeholder="Type a message..." required><button class="btn btn-coral">Send</button></form>@else<div style="margin:auto;color:#888;">Select a contact to start messaging.</div>@endif
    </div>
</div>
@endsection
