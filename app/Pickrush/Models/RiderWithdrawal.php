<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * Money a rider took out of their wallet to the payout account on file. Payouts are simulated for
 * now: it is paid at once with a mock reference, and no money moves.
 */
class RiderWithdrawal extends Model
{
    use Audited, CompanyOwned;

    protected $table = 'pr_rider_withdrawals';

    protected array $companyThrough = ['rider_id' => 'pr_riders'];

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class)->withoutGlobalScopes();
    }
}
