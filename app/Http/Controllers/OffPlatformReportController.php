<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OffPlatform\OffPlatformMonitor;
use DomainException;
use Illuminate\Http\Request;

/** A buyer reports that the seller asked them to pay, chat or ship outside PickSell. */
class OffPlatformReportController extends Controller
{
    public function store(Request $request, Order $order, OffPlatformMonitor $monitor)
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $data = $request->validate(['details' => 'required|string|min:10|max:2000'], ['details.min' => 'Tell us what the seller asked (at least 10 characters).']);

        try {
            $monitor->buyerReport($request->user(), $order, $data['details']);
        } catch (DomainException $e) {
            return back()->withErrors(['off_platform' => $e->getMessage()]);
        }

        return back()->with('success', 'Thank you. PickSell will review this seller. Keep paying and chatting on PickSell so your order stays protected.');
    }
}
