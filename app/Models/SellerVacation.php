<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class SellerVacation extends Model
{
    protected $fillable = ['seller_id', 'starts_at', 'ends_at', 'ended_at'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'ended_at' => 'datetime'];

    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }

    /** When the vacation really stops: the return date, or earlier if the seller came back early. */
    public function lastsUntil(): CarbonInterface
    {
        return $this->ended_at && $this->ended_at->lt($this->ends_at) ? $this->ended_at : $this->ends_at;
    }

    /**
     * Calendar days the vacation covers inside $month: every date from the start up to the moment it stops
     * (back on Nov 5 covers through Nov 4; coming back mid-day still uses that day).
     */
    public function daysIn(CarbonInterface $month): int
    {
        $first = $this->starts_at->copy()->startOfDay()->max($month->copy()->startOfMonth());
        $last = $this->lastsUntil()->copy()->subSecond()->startOfDay()->min($month->copy()->endOfMonth()->startOfDay());

        return $last->lt($first) ? 0 : (int) $first->diffInDays($last) + 1;
    }
}
