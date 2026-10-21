<?php

namespace App\Pickrush\Support;

use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;

/**
 * Who is acting right now inside Pickrush: a signed-in staff member, a rider using the rider app
 * (or applying in it), or the system (bridge from Picksell, scheduled jobs, console).
 */
final class Actor
{
    private static ?self $override = null;

    public function __construct(
        public readonly string $type,
        public readonly ?int $id = null,
        public readonly ?int $hubId = null,
    ) {}

    public static function current(): self
    {
        if (self::$override) {
            return self::$override;
        }
        $staff = self::staff();
        if ($staff) {
            return new self('staff', $staff->id, $staff->hub_id);
        }
        $rider = self::rider();

        return $rider ? new self('rider', $rider->id, $rider->station_hub_id) : new self('system');
    }

    /** The rider signed in to the rider app on this request, if any. */
    public static function rider(): ?Rider
    {
        return app()->bound('pickrush.rider') ? app('pickrush.rider') : null;
    }

    /** Runs $work with $actor recorded as the one acting (an applicant has no account yet). */
    public static function actingAs(self $actor, callable $work): mixed
    {
        $previous = self::$override;
        self::$override = $actor;
        try {
            return $work();
        } finally {
            self::$override = $previous;
        }
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
