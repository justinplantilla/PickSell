<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $delivery = $this->delivery;
        $order = $delivery->order;
        $buyer = $order->buyer;

        return [
            'id' => $this->id,
            'assignment_status' => $this->status,
            'offered_at' => $this->offered_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'delivery' => [
                'id' => $delivery->id,
                'tracking_number' => $delivery->tracking_number,
                'status' => $delivery->status,
                'delivery_attempts' => $delivery->delivery_attempts,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'tracking_status' => $order->tracking_status,
                    'waybill_number' => $order->waybill_number,
                    'items' => [[
                        'name' => $order->product_name,
                        'quantity' => $order->quantity,
                        'amount' => $order->amount,
                    ]],
                ],
                'recipient' => $buyer ? [
                    'name' => $buyer->full_name,
                    'contact_no' => $buyer->contact_no,
                    'province' => $buyer->province,
                    'municipality' => $buyer->municipality,
                    'barangay' => $buyer->barangay,
                    'street' => $buyer->street,
                    'house_no' => $buyer->house_no,
                ] : null,
                'logs' => $delivery->relationLoaded('logs')
                    ? $delivery->logs->map(fn ($log) => [
                        'actor_role' => $log->actor_role,
                        'from_status' => $log->from_status,
                        'to_status' => $log->to_status,
                        'note' => $log->note,
                        'proof_image_url' => $log->proof_image_url
                            ? route('api.v1.rider.deliveries.proof', [$delivery->id, $log->id])
                            : null,
                        'latitude' => $log->latitude,
                        'longitude' => $log->longitude,
                        'created_at' => $log->created_at?->toISOString(),
                    ])
                    : [],
            ],
        ];
    }
}
