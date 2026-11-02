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

    /**
     * Thanks a buyer for messaging a seller: once per buyer every AUTO_REPLY_QUIET_HOURS, or on vacation the
     * seller's away message once per buyer per vacation. Only earlier auto-replies count, so a seller who
     * already chatted with the buyer still sends one when the buyer comes back with a new question.
     */
    public function sellerAutoReply(User $buyer, User $seller): ?Message
    {
        if ($seller->role !== 'seller') {
            return null;
        }

        $onVacation = $seller->isOnVacation();
        $since = $onVacation ? $seller->vacation_started_at : now()->subHours(User::AUTO_REPLY_QUIET_HOURS);
        $alreadyReplied = Message::where('sender_id', $seller->id)->where('receiver_id', $buyer->id)
            ->where('body', 'like', Message::AUTO_REPLY_PREFIX.'%')
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->exists();
        if ($alreadyReplied) {
            return null;
        }

        return Message::create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'body' => Message::AUTO_REPLY_PREFIX.' '.($onVacation ? $seller->vacationAutoReply() : User::DEFAULT_THANK_YOU_MESSAGE),
            'read' => false,
        ]);
    }
}
