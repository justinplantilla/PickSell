<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Models\LogisticsClaim;
use App\Models\LogisticsIssue;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\Parcel;
use App\Services\Logistics\LogisticsIssueDesk;
use Illuminate\Http\Request;

/**
 * Issues PickSell raised with this company about its PickSell parcels: the ones waiting on the
 * company's reply first, then its open and resolved ones, and the claims charged against it. The
 * company reports here the returns its sellers did not take back, once the hold is over.
 */
class MarketplaceIssueController extends PortalController
{
    public function index()
    {
        $companyId = $this->staff()->company_id;
        $issues = fn () => LogisticsIssue::where('company_id', $companyId)->with(['order', 'parcel', 'claim', 'events' => fn ($q) => $q->where('action', 'escalated')]);

        return view('pickrush.hq.issues', [
            'waiting' => $issues()->where('status', 'escalated')->oldest('escalated_at')->get(),
            'recent' => $issues()->where('status', '!=', 'escalated')->latest()->limit(30)->get(),
            'claims' => LogisticsClaim::where('company_id', $companyId)->with('issue.parcel')->latest()->limit(30)->get(),
            'claimsTotal' => (float) LogisticsClaim::where('company_id', $companyId)->where('status', 'charged')->sum('amount'),
            'canRespond' => in_array($this->staff()->role->value, ['super_admin', 'support'], true),
            // Returns held for their seller past the hold, not reported yet.
            'unreported' => Parcel::withoutGlobalScopes()->where('company_id', $companyId)
                ->where(fn ($q) => $q->where(fn ($r) => $r->where('is_return', true)->whereIn('current_status', [ParcelStatus::ReturningToSender, ParcelStatus::Abandoned]))
                    // Buyer returns ("RET-…") the seller did not take.
                    ->orWhere(fn ($r) => $r->where('source', 'picksell')->where('external_ref', 'like', 'RET-%')
                        ->whereIn('current_status', [ParcelStatus::DeliveryFailed, ParcelStatus::Abandoned])))
                ->whereDate('return_refused_at', '<=', today()->subDays((int) config('pickrush.policies.return_hold_days')))
                ->whereNotIn('id', LogisticsIssue::where('company_id', $companyId)->where('type', 'return_refused')->whereNotNull('parcel_id')->select('parcel_id'))
                ->oldest('return_refused_at')->limit(50)->get(),
            'holdDays' => (int) config('pickrush.policies.return_hold_days'),
        ]);
    }

    /** Report a return the seller did not take back; PickSell decides the fault. */
    public function reportReturn(Request $request, LogisticsIssueDesk $desk)
    {
        $data = $request->validate([
            'tracking_no' => 'required|string|max:40',
            'description' => 'required|string|min:10|max:2000',
            'evidence' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ]);
        $parcel = Parcel::withoutGlobalScopes()->where('company_id', $this->staff()->company_id)
            ->where('tracking_no', strtoupper(trim($data['tracking_no'])))->first();
        if (! $parcel) {
            return back()->withErrors(['operation' => "No parcel {$data['tracking_no']} in your company."])->withInput();
        }

        return $this->attempt(fn () => $desk->reportRefusedReturn($this->staff(), $parcel, $data['description'], $request->file('evidence')),
            "{$parcel->tracking_no} reported to PickSell.");
    }

    public function respond(Request $request, int $issue, LogisticsIssueDesk $desk)
    {
        // Looked up within the company: another company's issue is simply not found.
        $issue = LogisticsIssue::where('company_id', $this->staff()->company_id)->findOrFail($issue);
        $data = $request->validate(['response' => 'required|string|min:10|max:2000']);

        return $this->attempt(fn () => $desk->respond($issue, $this->staff(), $data['response']), 'Reply sent to PickSell.');
    }
}
