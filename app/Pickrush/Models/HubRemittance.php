<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * Cash the station sends on to the company, with the deposit slip and a reference that can be used
 * only once, confirmed by HQ finance against the bank statement.
 *
 * A remittance HQ disputes waits for the station's answer: a top-up deposit for the shortfall (a
 * remittance whose settles_id points here; once HQ confirms enough of them this one is "settled"),
 * or a contest with an explanation, which sends it back to "submitted" for HQ to check again.
 */
class HubRemittance extends Model
{
    use Audited, CompanyOwned, HubOwned;

    public const SETTLED = 'settled';

    protected $table = 'pr_hub_remittances';

    protected array $companyThrough = ['station_hub_id' => 'pr_hubs'];

    protected $guarded = ['id'];

    protected string $hubColumn = 'station_hub_id';

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'remitted_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'bank_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'contested_at' => 'datetime',
    ];

    /** The deposit reference as compared for reuse: "bdo 0042" and "BDO-0042" are the same deposit. */
    public static function referenceKey(string $reference): string
    {
        return substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $reference)), 0, 60);
    }

    /**
     * What a disputed remittance still lacks at HQ: what the station declared, less what the bank
     * showed and the top-ups HQ confirmed. Zero when the dispute is not about a shortfall.
     */
    public function shortfall(): float
    {
        if ($this->bank_amount === null) {
            return 0.0;   // disputed before HQ recorded bank amounts: the station contests or HQ checks again
        }
        $topUps = (float) $this->topUps()->where('status', 'confirmed')->sum('remitted_amount');

        return max(0.0, round((float) $this->remitted_amount - (float) $this->bank_amount - $topUps, 2));
    }

    /** A top-up for this dispute that HQ has not checked yet. */
    public function pendingTopUp(): ?self
    {
        return $this->topUps()->where('status', 'submitted')->latest('id')->first();
    }

    /** The cash HQ actually holds from this remittance, for totals. */
    public static function receivedSql(): string
    {
        return "case when status = 'settled' then coalesce(bank_amount, 0) else remitted_amount end";
    }

    public function station()
    {
        return $this->belongsTo(Hub::class, 'station_hub_id');
    }

    public function riderRemittances()
    {
        return $this->hasMany(CodRemittance::class);
    }

    /** Deposits the station made to cover this remittance's shortfall. */
    public function topUps()
    {
        return $this->hasMany(self::class, 'settles_id')->withoutGlobalScopes();
    }

    /** The disputed remittance this top-up covers. */
    public function settles()
    {
        return $this->belongsTo(self::class, 'settles_id')->withoutGlobalScopes();
    }
}
