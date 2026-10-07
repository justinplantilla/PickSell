<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveRefundRequest;
use App\Http\Requests\RejectRefundRequest;
use App\Models\Refund;
use App\Services\Finance\RefundService;
use Illuminate\Http\Request;

class AdminRefundController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), Refund::STATUSES, true) ? $request->query('status') : 'all';
        $query = Refund::with(['order.buyer', 'order.seller', 'returnRequest', 'requester', 'approver'])->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $totals = Refund::query()
            ->selectRaw('status, count(*) as total, coalesce(sum(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('admin.refunds.index', [
            'refunds' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'totals' => $totals,
        ]);
    }

    public function approve(ApproveRefundRequest $request, Refund $refund, RefundService $refunds)
    {
        $refunds->approve($refund, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Refund approved and financial ledger entries recorded.');
    }

    public function reject(RejectRefundRequest $request, Refund $refund, RefundService $refunds)
    {
        $refunds->reject($refund, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Refund rejected and the decision was recorded.');
    }
}
