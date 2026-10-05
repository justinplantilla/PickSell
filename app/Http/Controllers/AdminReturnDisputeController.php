<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use App\Services\Admin\ReturnDisputeResolution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminReturnDisputeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        if (!in_array($status, ['all', 'open', 'resolved'], true)) {
            $status = 'open';
        }

        $query = ReturnRequest::whereIn('dispute_status', ['open', 'resolved'])
            ->with(['order', 'buyer', 'seller'])
            ->latest();
        if ($status !== 'all') {
            $query->where('dispute_status', $status);
        }

        $returnRequests = $query->paginate(15);

        return view('admin.returns.index', compact('returnRequests', 'status'));
    }

    public function show(ReturnRequest $returnRequest)
    {
        Gate::authorize('view', $returnRequest);
        $returnRequest->load(['order.product', 'buyer', 'seller', 'resolver', 'events.actor']);

        return view('admin.returns.show', compact('returnRequest'));
    }

    public function resolve(Request $request, ReturnRequest $returnRequest, ReturnDisputeResolution $resolution)
    {
        Gate::authorize('resolveDispute', $returnRequest);

        $data = $request->validate([
            'decision' => 'required|in:approve_return,uphold_rejection',
            'admin_notes' => 'required|string|max:2000',
        ]);
        $resolution->resolve($returnRequest, $data['decision'], $data['admin_notes']);

        return redirect()->route('admin.returns.show', $returnRequest)->with('success', 'Return dispute resolved.');
    }
}
