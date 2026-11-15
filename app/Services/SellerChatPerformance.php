<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;

class SellerChatPerformance
{
    /** First buyer inquiry per conversation in the past 30 days, allowing a full 24h to reply. */
    public function for(User $seller): array
    {
        $end = now();
        $cutoff = $end->copy()->subDay();
        $threads = [];
        $messages = Message::where('created_at', '>=', $end->copy()->subDays(30))
            ->where('created_at', '<=', $end)
            ->where(fn ($q) => $q
                ->where(fn ($in) => $in->where('receiver_id', $seller->id)->whereHas('sender', fn ($u) => $u->where('role', 'buyer')))
                ->orWhere(fn ($out) => $out->where('sender_id', $seller->id)->whereHas('receiver', fn ($u) => $u->where('role', 'buyer'))))
            ->orderBy('created_at')->orderBy('id')->cursor();

        foreach ($messages as $message) {
            if ($message->receiver_id === $seller->id) {
                if ($message->created_at <= $cutoff) {
                    $threads[$message->sender_id] ??= ['at' => $message->created_at, 'replied' => false];
                }
            } elseif (!str_starts_with($message->body, Message::AUTO_REPLY_PREFIX) && isset($threads[$message->receiver_id])) {
                if ($message->created_at <= $threads[$message->receiver_id]['at']->copy()->addDay()) {
                    $threads[$message->receiver_id]['replied'] = true;
                }
            }
        }

        $count = count($threads);
        $replied = count(array_filter($threads, fn ($thread) => $thread['replied']));
        return ['conversations' => $count, 'rate' => $count ? (int) round($replied / $count * 100) : null];
    }
}
