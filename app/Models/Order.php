<?php

namespace App\Models;

use App\Services\AdminNotificationService;
use App\Services\Orders\OrderLifecycleService;
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
        'order_number', 'product_id', 'variation_id', 'buyer_id', 'seller_id', 'order_shipment_id',
        'product_name', 'variation_label', 'quantity', 'amount', 'commission', 'commission_rate', 'status',
        'waybill_number', 'tracking_status',
        'packed_at', 'handed_over_at', 'delivered_at', 'confirmed_by_seller_at',
        'confirmed_at', 'preparing_at', 'ready_for_pickup_at', 'picked_up_at', 'sorting_received_at',
        'sorted_at', 'assigned_to_rider_at', 'out_for_delivery_at', 'completed_at',
        'rating', 'seller_rating', 'delivery_rating', 'delivery_rider_id', 'feedback',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'packed_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'delivered_at' => 'datetime',
        'confirmed_by_seller_at' => 'datetime',
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
                        subject: "order:{$order->id}",
                    );
                }
            }
        });
    }

    private function recordStatusChange(?string $from): void
    {
        // Only a marketplace account can be "changed_by". On the Pickrush portal the signed-in
        // user is a Pickrush staff member (another table), and the order follows the scan with
        // source "pickrush" and no person attached.
        $actor = auth()->user();
        $actor = $actor instanceof User ? $actor : null;
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

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** The parcel this order travels in with the seller's other items from the same checkout (none on older orders). */
    public function shipment()
    {
        return $this->belongsTo(OrderShipment::class, 'order_shipment_id');
    }

    /**
     * Who and where the parcel goes: the address chosen at checkout, copied onto the shipment, or
     * for orders from before the address book, the buyer's profile.
     *
     * @return array{name: ?string, phone: ?string, address: string}
     */
    public function deliverTo(): array
    {
        $shipment = $this->shipment;
        if ($shipment?->delivery_province) {
            return ['name' => $shipment->recipient_name, 'phone' => $shipment->recipient_phone, 'address' => (string) $shipment->delivery_address];
        }
        $buyer = $this->buyer;

        return [
            'name' => $buyer?->full_name,
            'phone' => $buyer?->contact_no,
            'address' => implode(', ', array_filter([$buyer?->house_no, $buyer?->street, $buyer?->barangay, $buyer?->municipality, $buyer?->province])),
        ];
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Photos the buyer attached when rating this order. */
    public function reviewPhotos()
    {
        return $this->hasMany(ProductReviewPhoto::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function returnRequest()
    {
        return $this->returns();
    }

    public function returns()
    {
        return $this->hasOne(ReturnRequest::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function financialTransactions()
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest()->oldest('id');
    }
}
