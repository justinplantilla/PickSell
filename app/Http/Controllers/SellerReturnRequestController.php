<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
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
        $returnRequest->load(['order.product', 'buyer', 'events.actor']);

        return view('seller.returns.show', compact('returnRequest'));
    }

    public function approve(Request $request, ReturnRequest $returnRequest)
    {
        $this->transition($returnRequest, 'requested');
        $data = $request->validate([
            'seller_note' => 'required|string|max:2000',
            'refund_amount' => 'required|numeric|min:0.01|max:' . $returnRequest->order->amount,
            'carrier' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:120',
        ]);
        $trackingStatus = !empty($data['tracking_number']) ? 'label_generated' : 'not_started';
        $returnRequest->update([
            'status' => 'awaiting_item',
            'seller_note' => $data['seller_note'],
            'refund_amount' => $data['refund_amount'],
            'carrier' => $data['carrier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'tracking_status' => $trackingStatus,
            'tracking_updated_at' => $trackingStatus === 'label_generated' ? now() : null,
            'approved_at' => now(),
        ]);
        $this->recordEvent($returnRequest, 'seller_approved', 'requested', 'awaiting_item', $data['seller_note'], [
            'refund_amount' => (float) $data['refund_amount'],
            'tracking_status' => $trackingStatus,
            'carrier' => $data['carrier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
        ]);

        return back()->with('success', 'Return approved. The buyer can now send the item back.');
    }

    public function reject(Request $request, ReturnRequest $returnRequest)
    {
        $this->transition($returnRequest, 'requested');
        $data = $request->validate(['seller_note' => 'required|string|max:2000']);
        $returnRequest->update([
            'status' => 'rejected',
            'dispute_status' => 'open',
            'seller_note' => $data['seller_note'],
            'rejected_at' => now(),
        ]);
        $this->recordEvent($returnRequest, 'seller_rejected', 'requested', 'rejected', $data['seller_note']);

        return back()->with('success', 'Request rejected and escalated to Admin for dispute review.');
    }

    public function markReceived(ReturnRequest $returnRequest)
    {
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

    public function markRefundDue(ReturnRequest $returnRequest)
    {
        $this->transition($returnRequest, 'received');
        $returnRequest->update(['status' => 'refund_due', 'refund_due_at' => now()]);
        $this->recordEvent($returnRequest, 'refund_due', 'received', 'refund_due', 'Seller marked the refund as due.');

        return back()->with('success', 'Refund marked as due.');
    }

    public function complete(ReturnRequest $returnRequest)
    {
        $this->transition($returnRequest, 'refund_due');
        $returnRequest->update(['status' => 'completed', 'completed_at' => now()]);
        $this->recordEvent($returnRequest, 'refund_completed', 'refund_due', 'completed', 'Seller confirmed the refund was sent.', [
            'refund_amount' => (float) $returnRequest->refund_amount,
        ]);

        return back()->with('success', 'Refund marked as completed.');
    }

    public function updateTracking(Request $request, ReturnRequest $returnRequest)
    {
        $this->transition($returnRequest, 'awaiting_item');
        $data = $request->validate([
            'tracking_status' => 'required|in:label_generated,in_transit,delivered_to_seller',
            'carrier' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:120',
        ]);

        $returnRequest->update([
            'tracking_status' => $data['tracking_status'],
            'carrier' => $data['carrier'] ?? $returnRequest->carrier,
            'tracking_number' => $data['tracking_number'] ?? $returnRequest->tracking_number,
            'tracking_updated_at' => now(),
        ]);
        $this->recordEvent(
            $returnRequest,
            'tracking_updated',
            $returnRequest->status,
            $returnRequest->status,
            'Return tracking updated to ' . str_replace('_', ' ', $data['tracking_status']) . '.',
            ['tracking_status' => $data['tracking_status'], 'carrier' => $returnRequest->carrier, 'tracking_number' => $returnRequest->tracking_number],
        );

        return back()->with('success', 'Return tracking details updated.');
    }

    private function transition(ReturnRequest $returnRequest, string $expectedStatus): void
    {
        $this->authorizeSeller($returnRequest);
        abort_unless($returnRequest->status === $expectedStatus, 422, 'This return request is no longer in the expected state.');
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