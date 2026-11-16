<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveReturnRefundRequest;
use App\Http\Requests\ApproveReturnRequest;
use App\Http\Requests\InspectReturnRequest;
use App\Http\Requests\RejectReturnRequest;
use App\Models\ReturnRequest;
use App\Pickrush\Bridge\PicksellBridge;
use App\Services\Admin\ReturnService;
use App\Services\Orders\OrderReceipt;
use App\Services\Returns\ReturnFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** All return requests and their refunds, marketplace-wide (read-only; returns.view / refunds.view). */
class AdminReturnController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ReturnRequest::STATUSES, true) ? $request->query('status') : 'all';
        $search = trim((string) $request->query('search', ''));

        $query = ReturnRequest::with(['order', 'buyer', 'seller'])->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->whereHas('order', fn ($q) => $q->whereLike('order_number', "%{$search}%"));
        }

        return view('admin.returns.all', [
            'returnRequests' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'search' => $search,
            'statusCounts' => ReturnRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(ApproveReturnRequest $request, ReturnRequest $returnRequest, ReturnService $returns, ReturnFlow $flow)
    {
        $returns->approve($returnRequest, $request->user(), $request->validated('admin_notes'));
        $parcel = $flow->bookReturn($returnRequest->refresh());

        return back()->with('success', $parcel
            ? "Return approved. Pickrush will pick it up from the buyer (tracking no. {$parcel->tracking_no})."
            : 'Return approved, but the Pickrush pickup could not be booked. Try "Book pickup" again.');
    }

    /** Rejected for good: the order completes and the seller's held payment is released. */
    public function reject(RejectReturnRequest $request, ReturnRequest $returnRequest, ReturnService $returns, OrderReceipt $receipt)
    {
        $returns->reject($returnRequest, $request->user(), $request->validated('admin_notes'));
        $receipt->afterReturn($returnRequest->order, $request->user(), 'Return closed without a refund');

        return back()->with('success', 'Return request rejected and decision recorded.');
    }

    /** The seller did not take the return, it was lost, or the item is not worth bringing back: refund anyway. */
    public function refundWithoutItem(Request $request, ReturnRequest $returnRequest, ReturnFlow $flow)
    {
        Gate::authorize('refundWithoutItem', $returnRequest);
        $data = $request->validate(['admin_notes' => ['required', 'string', 'min:3', 'max:2000']]);
        $flow->refundWithoutItem($returnRequest, $request->user(), $data['admin_notes']);

        return back()->with('success', 'Refund requested without the item. Finance approves it, then sends it through GCash.');
    }

    /** Books the Pickrush return waybill again for an approved return whose booking failed. */
    public function bookPickup(ReturnRequest $returnRequest, ReturnFlow $flow)
    {
        abort_unless($returnRequest->status === 'awaiting_item' && ! PicksellBridge::returnParcel($returnRequest), 422, 'This return already has its Pickrush pickup.');
        $parcel = $flow->bookReturn($returnRequest);

        return back()->with($parcel ? 'success' : 'error', $parcel
            ? "Pickrush pickup booked (tracking no. {$parcel->tracking_no})."
            : 'The pickup still could not be booked. Check the buyer and seller addresses.');
    }

    public function inspect(InspectReturnRequest $request, ReturnRequest $returnRequest, ReturnService $returns)
    {
        $returns->inspect($returnRequest, $request->user(), $request->validated('admin_notes'));

        return back()->with('success', 'Returned item inspection recorded.');
    }

    public function approveRefund(ApproveReturnRefundRequest $request, ReturnRequest $returnRequest, ReturnService $returns)
    {
        $data = $request->validated();
        $returns->approveRefund($returnRequest, $request->user(), (float) $data['refund_amount'], $data['admin_notes']);

        return back()->with('success', 'Refund requested. Finance approves it, then sends it through GCash.');
    }
}
