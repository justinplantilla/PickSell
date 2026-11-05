<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderNotification;

/**
 * Keeps "online" honest: the rider app checks in while it runs (foreground or background), and an
 * on-shift rider whose app stopped checking in for Rider::IDLE_MINUTES is switched off duty.
 */
class RiderPresence
{
    /** A request from the rider app: switch them off first if they had gone quiet, then record it. */
    public function seen(Rider $rider): void
    {
        if ($rider->shift_status === 'on_shift' && $rider->isIdle()) {
            $this->goOffline($rider);
        }
        if (! $rider->last_seen_at || $rider->last_seen_at->lt(now()->subSeconds(30))) {
            $rider->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    /** Switch off every on-shift rider whose app went quiet. Returns how many. */
    public function expireIdle(): int
    {
        $count = 0;
        Rider::withoutGlobalScopes()->where('shift_status', 'on_shift')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes(Rider::IDLE_MINUTES)))
            ->each(function (Rider $rider) use (&$count): void {
                $this->goOffline($rider);
                $count++;
            });

        return $count;
    }

    private function goOffline(Rider $rider): void
    {
        $rider->update(['shift_status' => 'off_duty']);
        RiderNotification::send($rider, 'went_offline', 'You were set offline',
            'The rider app was closed for more than '.Rider::IDLE_MINUTES.' minutes, so no new tasks were sent to you. Go online again when you are ready.');
    }
}
