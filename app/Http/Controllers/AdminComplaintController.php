<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveComplaintRequest;
use App\Http\Requests\UpdateComplaintRequest;
use App\Models\Complaint;
use App\Models\User;
use App\Services\Admin\ComplaintResolution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminComplaintController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        if (! in_array($status, ['all', 'open', 'under_review', 'resolved', 'dismissed'], true)) {
            $status = 'all';
        }

        $query = Complaint::with(['filer', 'against', 'order']);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('admin.complaints', [
            'complaints' => $query->latest()->paginate(15),
            'status' => $status,
            'users' => User::whereIn('role', ['buyer', 'seller', 'courier'])->where('status', 'approved')->get(),
        ]);
    }

    public function show(Complaint $complaint)
    {
        Gate::authorize('view', $complaint);
        $complaint->load(['filer', 'against', 'order.buyer', 'order.seller', 'order.courier', 'order.product']);

        return view('admin.complaint-detail', compact('complaint'));
    }

    public function update(UpdateComplaintRequest $request, Complaint $complaint, ComplaintResolution $resolution)
    {
        $data = $request->validated();
        $resolution->update($complaint, $request->user(), $data['status'], $data['admin_notes'] ?? null);

        return back()->with('success', 'Complaint updated successfully.');
    }

    public function resolve(ResolveComplaintRequest $request, Complaint $complaint, ComplaintResolution $resolution)
    {
        $data = $request->validated();
        $resolution->resolve(
            $complaint,
            $request->user(),
            $data['resolution_type'],
            $data['resolution_notes'],
            isset($data['refund_amount']) ? (float) $data['refund_amount'] : null,
            $data['status'] ?? 'resolved',
        );

        return redirect()->route('admin.complaints.show', $complaint)->with('success', 'Complaint resolved and participants notified.');
    }
}
