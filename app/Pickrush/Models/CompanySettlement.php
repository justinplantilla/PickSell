<?php

namespace App\Pickrush\Models;

use App\Models\LogisticsClaim;
use App\Models\User;
use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * A logistics company paying PickSell for delivered PickSell parcels: COD less rider pay and the
 * company's share, plus claims PickSell charged it. Submitted by the company's HQ finance with the
 * deposit slip; a PickSell admin confirms it, or disputes it with what the bank showed. A disputed
 * settlement waits for the company to top up the shortfall (a settlement whose settles_id points
 * here; once enough is confirmed this one is "settled") or to contest it (back to "submitted").
 */
class CompanySettlement extends Model
{
    use Audited, CompanyOwned;

    public const SETTLED = 'settled';

    protected $table = 'pr_company_settlements';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'claims_amount' => 'decimal:2',
        'bank_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'contested_at' => 'datetime',
    ];

    /** What a disputed settlement still lacks: the amount, less what the bank showed and the confirmed top-ups. */
    public function shortfall(): float
    {
        if ($this->bank_amount === null) {
            return 0.0;
        }
        $topUps = (float) $this->topUps()->where('status', 'confirmed')->sum('amount');

        return max(0.0, round((float) $this->amount - (float) $this->bank_amount - $topUps, 2));
    }

    public function pendingTopUp(): ?self
    {
        return $this->topUps()->where('status', 'submitted')->latest('id')->first();
    }

    /** Settled with PickSell: confirmed, or a dispute covered by its top-ups. */
    public function isReceived(): bool
    {
        return in_array($this->status, ['confirmed', self::SETTLED], true);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parcels()
    {
        return $this->hasMany(Parcel::class)->withoutGlobalScopes();
    }

    public function claims()
    {
        return $this->hasMany(LogisticsClaim::class);
    }

    public function topUps()
    {
        return $this->hasMany(self::class, 'settles_id')->withoutGlobalScopes();
    }

    public function settles()
    {
        return $this->belongsTo(self::class, 'settles_id')->withoutGlobalScopes();
    }

    public function submitter()
    {
        return $this->belongsTo(Staff::class, 'submitted_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
