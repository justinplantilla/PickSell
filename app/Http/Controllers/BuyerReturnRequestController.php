<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\Labels;
use App\Services\Returns\ReturnFlow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuyerReturnRequestController extends Controller
{
    /** Shared with the buyer app (BuyerApi\OrderController::requestReturn). */
    public static function rules(Order $order): array
    {
        return [
            // Seller-fault reasons only; anything else goes through Contact Support.
            'reason' => ['required', Rule::in(ReturnRequest::BUYER_REASONS)],
            'details' => 'required|string|max:2000',
            'quantity' => 'sometimes|integer|min:1|max:'.$order->quantity,
            // Where PickSell sends the refund.
            'gcash_number' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'gcash_name' => 'required|string|max:120',
        ];
    }

    public static function messages(): array
    {
        return ['gcash_number.regex' => 'GCash number must be 11 digits starting with 09, e.g. 09171234567.'];
    }

    public function store(Request $request, Order $order, ReturnFlow $flow)
    {
        abort_if($order->buyer_id !== auth()->id(), 404);

        $data = $request->validate(self::rules($order) + [
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,webm|max:10240',
        ], self::messages());
        $flow->request($order, $request->user(), $data, $request->file('attachments', []));

        return redirect()->route('buyer.orders')->with('success', 'Return/refund request sent to the seller.');
    }

    /** The Pickrush 4×6 return label (barcode + QR) the buyer sticks on the package for the rider to scan. */
    public function waybill(Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 404);

        return self::waybillFor($order);
    }

    /** Shared with the buyer app (BuyerApi\OrderController::returnWaybill). */
    public static function waybillFor(Order $order)
    {
        $return = $order->returnRequest;
        abort_unless($return?->tracking_number && in_array($return->status, ['awaiting_item', 'received'], true), 404);
        $parcel = Parcel::where('tracking_no', $return->tracking_number)->firstOrFail();

        return app(Labels::class)->parcel($parcel);
    }
}
