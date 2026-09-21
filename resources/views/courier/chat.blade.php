@extends('courier.layout')
@vite('resources/css/views/courier-chat.css')
@section('title', 'Chat')
@section('content')
<h1 class="blade-inline-1">Chat / Messaging</h1>
<div class="card blade-inline-2">
    <div class="blade-inline-3">
        <strong>Contacts</strong>
        <div class="blade-inline-4">@forelse($contacts as $contact)<a href="{{ route('courier.chat', ['user' => $contact->id]) }}" style="padding:0.55rem;border-radius:6px;background:{{ optional($activeUser)->id === $contact->id ? '#fff3f0' : '#fafafa' }};">{{ $contact->full_name }}<small class="blade-inline-5">{{ ucfirst($contact->role) }}</small></a>@empty<span class="blade-inline-6">No conversations yet.</span>@endforelse</div>
    </div>
    <div class="blade-inline-7">
        @if($activeUser)<div class="blade-inline-8">{{ $activeUser->full_name }}</div><div class="blade-inline-9">@forelse($messages as $message)<div style="margin-bottom:0.6rem;text-align:{{ $message->sender_id === auth()->id() ? 'right' : 'left' }};"><span style="display:inline-block;padding:0.5rem 0.7rem;border-radius:8px;background:{{ $message->sender_id === auth()->id() ? '#fff3f0' : '#f4f4f4' }};">{{ $message->body }}</span></div>@empty<span class="blade-inline-10">No messages yet.</span>@endforelse</div><form method="POST" action="{{ route('courier.chat.send') }}" class="blade-inline-11">@csrf<input type="hidden" name="receiver_id" value="{{ $activeUser->id }}"><input class="form-control blade-inline-12" name="message" placeholder="Type a message..." required><button class="btn btn-coral">Send</button></form>@else<div class="blade-inline-13">Select a contact to start messaging.</div>@endif
    </div>
</div>
@endsection
