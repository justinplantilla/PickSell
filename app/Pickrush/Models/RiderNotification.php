<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Model;

/** Something the rider app shows under the bell, e.g. a new pickup batch to accept. */
class RiderNotification extends Model
{
    use CompanyOwned;

    protected $table = 'pr_rider_notifications';

    protected array $companyThrough = ['rider_id' => 'pr_riders'];

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public static function send(Rider $rider, string $type, string $title, ?string $body = null, array $data = []): self
    {
        return self::create(['rider_id' => $rider->id, 'type' => $type, 'title' => $title, 'body' => $body, 'data' => $data ?: null]);
    }
}
