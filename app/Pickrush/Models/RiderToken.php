<?php

namespace App\Pickrush\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A rider app sign-in. Only the sha256 of the bearer token is stored. */
class RiderToken extends Model
{
    protected $table = 'pr_rider_tokens';

    protected $guarded = ['id'];

    protected $casts = ['last_used_at' => 'datetime'];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    /** Creates a sign-in for the rider and returns the plain token, shown to the app once. */
    public static function issue(Rider $rider): string
    {
        $plain = Str::random(64);
        self::create(['rider_id' => $rider->id, 'token_hash' => hash('sha256', $plain), 'last_used_at' => now()]);

        return $plain;
    }

    public static function findByPlain(string $plain): ?self
    {
        return self::where('token_hash', hash('sha256', $plain))->first();
    }
}
