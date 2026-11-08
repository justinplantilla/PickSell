<?php

namespace App\Pickrush\Models;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Support\Actor;
use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use App\Pickrush\Support\ParcelHubScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * One physical parcel. Status, current hub and next hub are cached from the event log; change
 * them only through ParcelLedger::record(), never by updating these columns directly.
 */
#[ScopedBy(ParcelHubScope::class)]
class Parcel extends Model
{
    use Audited, CompanyOwned;

    protected $table = 'pr_parcels';

    protected $guarded = ['id'];

    protected $casts = [
        'current_status' => ParcelStatus::class,
        'cod_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'rider_pay' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'logistics_share' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'needs_address_fix' => 'boolean',
        'is_return' => 'boolean',
        'delivered_at' => 'datetime',
        'sorted_at' => 'datetime',
    ];

    public function events()
    {
        return $this->hasMany(ParcelEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function originHub()
    {
        return $this->belongsTo(Hub::class, 'origin_hub_id');
    }

    public function destHub()
    {
        return $this->belongsTo(Hub::class, 'dest_hub_id');
    }

    public function currentHub()
    {
        return $this->belongsTo(Hub::class, 'current_hub_id');
    }

    public function nextHub()
    {
        return $this->belongsTo(Hub::class, 'next_hub_id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class)->latest('id');
    }

    public function openAssignment()
    {
        return $this->hasOne(Assignment::class)->whereIn('status', Assignment::OPEN)->latestOfMany();
    }

    /** The newest rider task, open or not (e.g. to show that a rider declined the pickup). */
    public function latestAssignment()
    {
        return $this->hasOne(Assignment::class)->latestOfMany();
    }

    /** Where the rider takes it: the buyer, or the seller when it is coming back. */
    public function dropOffBarangayCode(): ?string
    {
        return $this->is_return ? $this->pickup_barangay_code : $this->delivery_barangay_code;
    }

    /**
     * A parcel on its way to the viewer's hub. Its full tracking number stays hidden there until
     * it is scanned in, so it can only be received off the label of the parcel itself.
     */
    public function isHiddenFrom(?Staff $staff = null): bool
    {
        $staff ??= Actor::staff();

        return $staff && ! $staff->isCompany() && $this->next_hub_id === $staff->hub_id
            && in_array($this->current_status, [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation], true);
    }

    /** "PR••••••K7Q2": enough to tell parcels apart, not enough to receive one. */
    public function maskedTrackingNo(): string
    {
        return substr($this->tracking_no, 0, 2).'••••••'.substr($this->tracking_no, -4);
    }

    /** The tracking number as this viewer may see it. */
    public function trackingNoFor(?Staff $staff = null): string
    {
        return $this->isHiddenFrom($staff) ? $this->maskedTrackingNo() : $this->tracking_no;
    }

    public function getRouteKeyName(): string
    {
        return 'tracking_no';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** A new parcel without a company in context takes the company of its hub. */
    protected function inferCompanyId(): ?int
    {
        $hubId = $this->origin_hub_id ?? $this->dest_hub_id;

        return $hubId ? Hub::withoutGlobalScopes()->whereKey($hubId)->value('company_id') : null;
    }
}
