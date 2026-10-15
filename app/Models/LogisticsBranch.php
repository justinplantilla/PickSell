<?php

namespace App\Models;

use App\Support\LogisticsDomains;
use App\Support\PlaceName;
use Illuminate\Database\Eloquent\Model;

class LogisticsBranch extends Model
{
    protected $fillable = [
        'municipality_id', 'logistics_id', 'hub_type', 'parent_id', 'subdomain',
        'name', 'address', 'status',
    ];

    public function municipality() { return $this->belongsTo(Municipality::class); }
    public function logistics() { return $this->belongsTo(User::class, 'logistics_id'); }
    public function assignedOperator() { return $this->hasOne(User::class, 'assigned_hub_id'); }
    public function riderAssignments() { return $this->hasMany(BranchRider::class, 'branch_id'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function originOrders() { return $this->hasMany(Order::class, 'origin_branch_id'); }
    public function destinationOrders() { return $this->hasMany(Order::class, 'destination_branch_id'); }

    public function isProvinceHub(): bool
    {
        return $this->hub_type === 'province_hub';
    }

    public function isStationHub(): bool
    {
        return $this->hub_type === 'station_hub';
    }

    /**
     * Pending courier applications from this station's town, oldest first. Stations review them.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function pendingCourierApplications()
    {
        $municipality = $this->municipality;
        if (! $municipality) {
            return collect();
        }

        return User::query()
            ->where('role', 'courier')
            ->where('status', 'pending')
            ->whereRaw('LOWER(province) = ?', [mb_strtolower(trim((string) $municipality->province))])
            ->oldest()
            ->get()
            ->filter(fn (User $applicant) => PlaceName::same($applicant->municipality, $municipality->name))
            ->values();
    }

    /** e.g. hub-cebu.pick-sell.shop or station-mandaue.pick-sell.shop; null without a subdomain. */
    public function portalHost(): ?string
    {
        if (! $this->subdomain) {
            return null;
        }

        return $this->isProvinceHub()
            ? LogisticsDomains::provinceHost($this->subdomain)
            : LogisticsDomains::stationHost($this->subdomain);
    }

    public function portalUrl(): string
    {
        $host = $this->portalHost();

        return $host ? 'https://'.$host.'/logistics/dashboard' : url('/logistics/dashboard');
    }
}