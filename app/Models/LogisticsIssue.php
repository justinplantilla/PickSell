<?php

namespace App\Models;

use App\Pickrush\Models\Company;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A problem with a registered logistics company's handling of a parcel, followed up by admin. */
class LogisticsIssue extends Model
{
    public const TYPES = [
        // Raised by the company's own hubs (parcel exceptions)
        'missing' => 'Missing from trip',
        'damaged' => 'Damaged packaging',
        'mis_sorted' => 'Mis-sorted',
        'unlabeled' => 'Unlabeled or unreadable waybill',
        'no_route' => 'No route',
        // From the parcel's status
        'lost' => 'Parcel lost',
        'returned' => 'Returned to seller (failed delivery)',
        // Reported by buyers and sellers
        'not_received' => 'Marked delivered but not received',
        'damaged_item' => 'Item arrived damaged',
        'late' => 'Delivery very late',
        'rider_conduct' => 'Rider conduct',
        'pickup_missed' => 'Pickup not done',
        'other' => 'Other',
    ];

    /** What buyers and sellers can report from their order page. */
    public const BUYER_REPORTS = ['not_received', 'damaged_item', 'late', 'rider_conduct', 'other'];

    public const SELLER_REPORTS = ['pickup_missed', 'damaged', 'rider_conduct', 'late', 'other'];

    /** Issue types where the carrier can owe the item and fee (a claim). */
    public const CLAIMABLE = ['lost', 'missing', 'damaged', 'damaged_item', 'not_received'];

    public const FAULTS = [
        'carrier' => 'Logistics company at fault',
        'seller' => 'Seller at fault',
        'buyer' => 'Buyer at fault',
        'none' => 'No one at fault / explained',
    ];

    public const STATUSES = ['open' => 'Open', 'escalated' => 'Waiting on company', 'resolved' => 'Resolved'];

    /** Open longer than this is overdue. */
    public const OVERDUE_HOURS = 48;

    protected $guarded = ['id'];

    protected $casts = [
        'escalated_at' => 'datetime',
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment()
    {
        return $this->belongsTo(OrderShipment::class, 'order_shipment_id');
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class)->withoutGlobalScopes();
    }

    public function exception()
    {
        return $this->belongsTo(ParcelException::class, 'exception_id')->withoutGlobalScopes();
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function events()
    {
        return $this->hasMany(LogisticsIssueEvent::class)->orderBy('id');
    }

    public function claim()
    {
        return $this->hasOne(LogisticsClaim::class);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'escalated']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unresolved()->where('created_at', '<', now()->subHours(self::OVERDUE_HOURS));
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'resolved' && $this->created_at?->lt(now()->subHours(self::OVERDUE_HOURS));
    }

    public function isClaimable(): bool
    {
        return in_array($this->type, self::CLAIMABLE, true);
    }
}
