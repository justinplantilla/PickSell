<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BuyerReturnRequestController extends Controller
{
    public function store(Request $request, Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 404);
        abort_unless(in_array($order->status, ['delivered', 'completed'], true), 422, 'Returns can only be requested for delivered orders.');
        abort_if($order->returnRequest()->exists(), 409, 'A return request already exists for this order.');

        $data = $request->validate([
            'reason' => 'required|in:damaged,wrong_item,wrong_size,not_as_described,other',
            'details' => 'required|string|max:2000',
            'quantity' => 'sometimes|integer|min:1|max:' . $order->quantity,
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,webm|max:10240',
        ]);

        $quantity = (int) ($data['quantity'] ?? $order->quantity);
        $attachments = collect($request->file('attachments', []))
            ->map(fn ($attachment) => $attachment->store('return-requests', 'public'))
            ->all();

        $returnRequest = ReturnRequest::create([
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id,
            'reason' => $data['reason'],
            'details' => $data['details'],
            'quantity' => $quantity,
            'refund_amount' => round(((float) $order->amount / max((int) $order->quantity, 1)) * $quantity, 2),
            'attachments' => $attachments,
            'status' => 'requested',
        ]);

        ReturnRequestEvent::create([
            'return_request_id' => $returnRequest->id,
            'actor_user_id' => $order->buyer_id,
            'event_type' => 'request_submitted',
            'from_status' => null,
            'to_status' => 'requested',
            'notes' => $data['details'],
            'metadata' => ['reason' => $data['reason'], 'quantity' => $quantity],
        ]);

        return redirect()->route('buyer.orders')->with('success', 'Return/refund request sent to the seller.');
    }
}