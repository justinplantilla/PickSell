<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Pickrush\Bridge\PicksellBridge;
use App\Services\Returns\ReturnFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SellerReturnRequestController extends Controller
{
    public function pendingCount()
    {
        $count = Schema::hasTable('return_requests')
            ? ReturnRequest::where('seller_id', auth()->id())->where('status', 'requested')->count()
            : 0;

        return response()->json(['count' => $count]);
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        if ($status !== 'all' && !in_array($status, ReturnRequest::STATUSES, true)) {
            $status = 'all';
        }

        if (!Schema::hasTable('return_requests')) {
            return view('seller.returns.index', [
                'returnRequests' => collect(),
                'status' => $status,
                'returnTableReady' => false,
            ]);
        }

        $query = ReturnRequest::where('seller_id', auth()->id())
            ->with(['order', 'buyer'])
            ->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $returnRequests = $query->paginate(15);

        return view('seller.returns.index', [
            'returnRequests' => $returnRequests,
            'status' => $status,
            'returnTableReady' => true,
        ]);
    }

    public function show(ReturnRequest $returnRequest)
    {
        $this->authorizeSeller($returnRequest);
        $returnRequest->load(['order.product', 'buyer', 'events.actor', 'latestRefund']);

        return view('seller.returns.show', compact('returnRequest'));
    }

    /** Approve: PickSell books the Pickrush return waybill (pickup at the buyer); the seller pays its fee on hand-over. */
    public function approve(Request $request, ReturnRequest $returnRequest, ReturnFlow $flow)
    {
        $this->authorizeSeller($returnRequest);
        $data = $request->validate(['seller_note' => 'required|string|max:2000']);
        $returnRequest = $flow->approve($returnRequest, auth()->user(), $data['seller_note']);

        return back()->with('success', $returnRequest->tracking_number
            ? "Return approved. Pickrush will pick it up from the buyer (tracking no. {$returnRequest->tracking_number})."
            : 'Return approved. PickSell is booking the Pickrush pickup.');
    }

    public function reject(Request $request, ReturnRequest $returnRequest, ReturnFlow $flow)
    {
        $this->authorizeSeller($returnRequest);
        $data = $request->validate(['seller_note' => 'required|string|max:2000']);
        $flow->reject($returnRequest, auth()->user(), $data['seller_note']);

        return back()->with('success', 'Request rejected and escalated to Admin for dispute review.');
    }

    /**
     * Pickrush marks the return received when it hands it over. Only a return without a Pickrush
     * waybill (its booking failed and the item came another way) is marked received by the seller.
     */
    public function markReceived(ReturnRequest $returnRequest)
    {
        $this->authorizeSeller($returnRequest);
        abort_if(PicksellBridge::returnParcel($returnRequest), 422, 'Pickrush marks this return received when the rider hands it to you.');
        $this->transition($returnRequest, 'awaiting_item');
        $returnRequest->update([
            'status' => 'received',
            'tracking_status' => 'delivered_to_seller',
            'tracking_updated_at' => now(),
            'received_at' => now(),
        ]);
        $this->recordEvent($returnRequest, 'item_received', 'awaiting_item', 'received', 'Seller confirmed receipt of the returned item.');

        return back()->with('success', 'Returned item marked as received.');
    }

    /** The seller checked the returned item: OK sends the refund to PickSell Finance; not OK goes to PickSell. */
    public function inspect(Request $request, ReturnRequest $returnRequest, ReturnFlow $flow)
    {
        $this->authorizeSeller($returnRequest);
        $data = $request->validate([
            'result' => 'required|in:passed,failed',
            'inspection_note' => 'required|string|min:5|max:2000',
        ]);
        $flow->inspect($returnRequest, auth()->user(), $data['result'] === 'passed', $data['inspection_note']);

        return back()->with('success', $data['result'] === 'passed'
            ? 'Item checked. PickSell will send the buyer\'s refund through GCash.'
            : 'Problem reported. PickSell will review the return and decide.');
    }

    private function transition(ReturnRequest $returnRequest, string $expectedStatus): void
    {
        $this->authorizeSeller($returnRequest);
        abort_unless($returnRequest->status === $expectedStatus && $returnRequest->dispute_status !== 'open', 422, 'This return request is no longer in the expected state.');
    }

    private function authorizeSeller(ReturnRequest $returnRequest): void
    {
        abort_if($returnRequest->seller_id !== auth()->id(), 403);
    }

    private function recordEvent(ReturnRequest $returnRequest, string $type, ?string $from, string $to, ?string $notes, ?array $metadata = null): void
    {
        ReturnRequestEvent::create([
            'return_request_id' => $returnRequest->id,
            'actor_user_id' => auth()->id(),
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);
    }
}