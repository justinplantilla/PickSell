<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class AdminNotificationService
{
    public function notifyAdmins(
        string $event,
        string $title,
        string $message,
        string $url,
        ?int $exceptActorId = null,
        string $priority = 'normal',
    ): void
    {
        $payload = json_encode([
            'event' => $event,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'priority' => $priority,
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        User::query()
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->when($exceptActorId, fn ($query) => $query->whereKeyNot($exceptActorId))
            ->orderBy('id')
            ->each(function (User $admin) use ($payload): void {
                $admin->notifications()->create([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\AdminActivity',
                    'data' => $payload,
                ]);
            });
    }
}
