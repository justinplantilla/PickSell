<?php

namespace App\Pickrush\Support;

use App\Pickrush\Models\Staff;

/**
 * Who is acting right now inside Pickrush: a signed-in staff member, or the system
 * (bridge from Picksell, scheduled jobs, console). Riders are added with the rider API.
 */
final class Actor
{
    public function __construct(
        public readonly string $type,
        public readonly ?int $id = null,
        public readonly ?int $hubId = null,
    ) {}

    public static function current(): self
    {
        $staff = self::staff();

        return $staff ? new self('staff', $staff->id, $staff->hub_id) : new self('system');
    }

    public static function staff(): ?Staff
    {
        $user = auth('pickrush')->user();

        return $user instanceof Staff ? $user : null;
    }

    public static function system(): self
    {
        return new self('system');
    }

    public static function picksell(): self
    {
        return new self('picksell');
    }
}
