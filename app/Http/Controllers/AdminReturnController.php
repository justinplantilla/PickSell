<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveReturnRefundRequest;
use App\Http\Requests\ApproveReturnRequest;
use App\Http\Requests\InspectReturnRequest;
use App\Http\Requests\RejectReturnRequest;
use App\Models\ReturnRequest;
use App\Services\Admin\ReturnService;
use Illuminate\Http\Request;

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
            $query->whereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$search}%"));
        }

        return view('admin.returns.all', [
            'returnRequests' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'search' => $search,
            'statusCounts' => ReturnRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(ApproveReturnRequest $request, ReturnRequest $returnRequest, ReturnService $returns)
    {
        $returns->approve($returnRequest, $request->user(), $request->validated('admin_notes'));

        return back()->with('success', 'Return approved. The buyer may now send the item back.');
    }

    public function reject(RejectReturnRequest $request, ReturnRequest $returnRequest, ReturnService $returns)
    {
        $returns->reject($returnRequest, $request->user(), $request->validated('admin_notes'));

        return back()->with('success', 'Return request rejected and decision recorded.');
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

        return back()->with('success', 'Refund approved. The seller can now issue the refund.');
    }
}
