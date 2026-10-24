<?php

namespace App\Pickrush\Models;

use Illuminate\Database\Eloquent\Model;

/** Something the rider app shows under the bell, e.g. a new pickup batch to accept. */
class RiderNotification extends Model
{
    protected $table = 'pr_rider_notifications';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public static function send(Rider $rider, string $type, string $title, ?string $body = null, array $data = []): self
    {
        return self::create(['rider_id' => $rider->id, 'type' => $type, 'title' => $title, 'body' => $body, 'data' => $data ?: null]);
    }
}
