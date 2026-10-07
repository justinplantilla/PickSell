<?php

namespace App\Models;

use App\Services\Orders\OrderLifecycleService;
use App\Services\AdminNotificationService;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_LIFECYCLE = [
        'placed',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'picked_up',
        'at_sorting_center',
        'sorted',
        'assigned_to_rider',
        'out_for_delivery',
        'delivered',
        'completed',
        'delivery_failed',
        'returned',
        'cancelled',
    ];

    protected $fillable = [
        'order_number', 'product_id', 'buyer_id', 'seller_id', 'logistics_id', 'courier_id',
        'origin_branch_id', 'destination_branch_id', 'destination_barangay_id',
        'product_name', 'quantity', 'amount', 'commission', 'commission_rate', 'status',
        'waybill_number', 'tracking_status',
        'packed_at', 'handed_over_at', 'delivered_at', 'confirmed_by_seller_at', 'assigned_at',
        'confirmed_at', 'preparing_at', 'ready_for_pickup_at', 'picked_up_at', 'sorting_received_at',
        'sorted_at', 'assigned_to_rider_at', 'out_for_delivery_at', 'completed_at',
        'rating', 'feedback',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'packed_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'delivered_at' => 'datetime',
        'confirmed_by_seller_at' => 'datetime',
        'assigned_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_for_pickup_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'sorting_received_at' => 'datetime',
        'sorted_at' => 'datetime',
        'assigned_to_rider_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Context for the next status change (not persisted on orders); see OrderLifecycleService. */
    public ?int $statusChangedBy = null;
    public ?string $statusChangeSource = null;
    public ?string $statusChangeReason = null;

    /**
     * Every status change — from any portal — stamps its lifecycle timestamp (first time only)
     * and writes an order_status_histories row, so the full lifecycle is always visible.
     */
    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            $column = OrderLifecycleService::TIMESTAMPS[$order->status] ?? null;
            if ($order->isDirty('status') && $column && $order->{$column} === null) {
                $order->{$column} = now();
            }
        });

        static::created(fn (Order $order) => $order->recordStatusChange(null));

        static::updated(function (Order $order): void {
            if ($order->wasChanged('status')) {
                $order->recordStatusChange($order->getOriginal('status'));

                $notifications = app(AdminNotificationService::class);
                if ($order->status === 'delivery_failed') {
                    $notifications->notifyAdmins(
                        'delivery.failed',
                        'Delivery failed',
                        "Delivery failed for order {$order->order_number}.",
                        route('admin.orders.show', ['order' => $order], false),
                        priority: 'critical',
                    );
                } elseif ($order->status === 'sorted' && $order->courier_id === null) {
                    $notifications->notifyAdmins(
                        'parcel.unassigned',
                        'Unassigned parcel',
                        "Order {$order->order_number} was sorted but has no assigned courier.",
                        route('admin.orders.show', ['order' => $order], false),
                    );
                }
            }
        });
    }

    private function recordStatusChange(?string $from): void
    {
        $actor = auth()->user();
        OrderStatusHistory::create([
            'order_id' => $this->id,
            'changed_by' => $this->statusChangedBy ?? $actor?->id,
            'source' => $this->statusChangeSource ?? ($actor?->role ?? 'system'),
            'from_status' => $from,
            'to_status' => $this->status,
            'reason' => $this->statusChangeReason,
        ]);
        $this->statusChangedBy = $this->statusChangeSource = $this->statusChangeReason = null;
    }

    public static function statusLifecycle(): array
    {
        return self::STATUS_LIFECYCLE;
    }

    public function buyer()   { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller()  { return $this->belongsTo(User::class, 'seller_id'); }
    public function logistics() { return $this->belongsTo(User::class, 'logistics_id'); }
    public function courier() { return $this->belongsTo(User::class, 'courier_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function originBranch() { return $this->belongsTo(LogisticsBranch::class, 'origin_branch_id'); }
    public function destinationBranch() { return $this->belongsTo(LogisticsBranch::class, 'destination_branch_id'); }
    public function destinationBarangay() { return $this->belongsTo(Barangay::class, 'destination_barangay_id'); }
    public function returnRequest() { return $this->hasOne(ReturnRequest::class); }
    public function complaints() { return $this->hasMany(Complaint::class); }
    public function parcelScans() { return $this->hasMany(ParcelScan::class)->latest('scanned_at')->latest('id'); }
    public function logisticsExceptions() { return $this->hasMany(LogisticsException::class)->latest(); }
    public function financialTransactions() { return $this->hasMany(FinancialTransaction::class); }
    public function statusHistories() { return $this->hasMany(OrderStatusHistory::class)->oldest()->oldest('id'); }
}
