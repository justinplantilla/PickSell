<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Model;

/** A problem a rider reported from the app (vehicle, accident...). The station resolves it. */
class RiderReport extends Model
{
    use Audited;

    protected $table = 'pr_rider_reports';

    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function rider()
    {
        return $this->belongsTo(Rider::class)->withoutGlobalScopes();
    }

    public function batch()
    {
        return $this->belongsTo(RiderBatch::class, 'batch_id');
    }

    public function label(): string
    {
        return config("pickrush.rider_problems.{$this->type}", ucfirst($this->type));
    }
}
