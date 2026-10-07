<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MessagingService
{
    public function sendAdminMessage(User $sender, User $recipient, string $body): Message
    {
        Gate::authorize('sendMessage', [Message::class, $recipient]);

        return DB::transaction(fn (): Message => Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => $body,
            'read' => false,
        ]));
    }
}
