<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use Illuminate\Http\Request;

/** All return requests and their refunds, marketplace-wide (read-only; returns.view / refunds.view). */
class AdminReturnController extends Controller
{
    public const REFUND_STATUSES = ['refund_due', 'completed'];

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

    public function refunds(Request $request)
    {
        $status = in_array($request->query('status'), self::REFUND_STATUSES, true) ? $request->query('status') : 'all';

        $query = ReturnRequest::with(['order', 'buyer', 'seller'])
            ->whereIn('status', $status === 'all' ? self::REFUND_STATUSES : [$status])
            ->latest('updated_at');

        $totals = ReturnRequest::whereIn('status', self::REFUND_STATUSES)
            ->selectRaw('status, count(*) as total, coalesce(sum(refund_amount), 0) as amount')
            ->groupBy('status')->get()->keyBy('status');

        return view('admin.refunds.index', [
            'refunds' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'totals' => $totals,
        ]);
    }
}
