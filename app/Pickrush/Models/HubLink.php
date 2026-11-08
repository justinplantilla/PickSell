<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A two-way land or ferry link between neighboring province hubs; trips between them may run. */
class HubLink extends Model
{
    use Audited, CompanyOwned;

    public const MODES = ['land' => 'Land', 'sea' => 'Ferry'];

    protected $table = 'pr_hub_links';

    protected $guarded = ['id'];

    protected $casts = ['travel_hours' => 'float', 'is_active' => 'boolean'];

    public function hubA()
    {
        return $this->belongsTo(Hub::class, 'hub_a_id');
    }

    public function hubB()
    {
        return $this->belongsTo(Hub::class, 'hub_b_id');
    }

    /** The pair is stored once, lower id first. */
    public static function between(Hub $one, Hub $other): ?self
    {
        [$a, $b] = self::pair($one->id, $other->id);

        return self::where('hub_a_id', $a)->where('hub_b_id', $b)->first();
    }

    /** @return array{0: int, 1: int} */
    public static function pair(int $one, int $other): array
    {
        return $one < $other ? [$one, $other] : [$other, $one];
    }

    public function scopeTouching(Builder $query, Hub $hub): Builder
    {
        return $query->where(fn ($q) => $q->where('hub_a_id', $hub->id)->orWhere('hub_b_id', $hub->id));
    }

    public function otherEnd(Hub $hub): Hub
    {
        return $this->hub_a_id === $hub->id ? $this->hubB : $this->hubA;
    }
}
