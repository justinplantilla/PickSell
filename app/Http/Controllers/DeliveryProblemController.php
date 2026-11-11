<?php

namespace App\Http\Controllers;

use App\Models\LogisticsIssue;
use App\Models\Order;
use App\Services\Logistics\LogisticsIssueDesk;
use DomainException;
use Illuminate\Http\Request;

/** A buyer or seller reports a problem with the logistics company carrying their order. */
class DeliveryProblemController extends Controller
{
    /** Order statuses from which a delivery problem can be reported (the parcel is with the carrier, or was). */
    public const REPORTABLE = ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned'];

    public function store(Request $request, Order $order, LogisticsIssueDesk $desk)
    {
        $user = $request->user();
        abort_unless(in_array($user->id, [$order->buyer_id, $order->seller_id], true), 404);
        $types = $user->id === $order->buyer_id ? LogisticsIssue::BUYER_REPORTS : LogisticsIssue::SELLER_REPORTS;

        if (! in_array($order->status, self::REPORTABLE, true)) {
            return back()->withErrors(['delivery_problem' => 'You can report a delivery problem once the order is with the logistics company.']);
        }
        $data = $request->validate([
            'type' => 'required|in:'.implode(',', $types),
            'description' => 'required|string|min:10|max:2000',
            'evidence' => 'nullable|image|max:5120',
        ], [
            'description.min' => 'Describe what happened (at least 10 characters).',
        ]);

        try {
            $desk->report($user, $order, $data['type'], $data['description'], $request->file('evidence'));
        } catch (DomainException $e) {
            return back()->withErrors(['delivery_problem' => $e->getMessage()]);
        }

        return back()->with('success', 'Thanks, PickSell received your report and will follow it up with the logistics company.');
    }
}
