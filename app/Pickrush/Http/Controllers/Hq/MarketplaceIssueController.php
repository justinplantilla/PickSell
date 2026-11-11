<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Models\LogisticsClaim;
use App\Models\LogisticsIssue;
use App\Pickrush\Http\Controllers\PortalController;
use App\Services\Logistics\LogisticsIssueDesk;
use Illuminate\Http\Request;

/**
 * Issues PickSell raised with this company about its PickSell parcels: the ones waiting on the
 * company's reply first, then its open and resolved ones, and the claims charged against it.
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
        ]);
    }

    public function respond(Request $request, int $issue, LogisticsIssueDesk $desk)
    {
        // Looked up within the company: another company's issue is simply not found.
        $issue = LogisticsIssue::where('company_id', $this->staff()->company_id)->findOrFail($issue);
        $data = $request->validate(['response' => 'required|string|min:10|max:2000']);

        return $this->attempt(fn () => $desk->respond($issue, $this->staff(), $data['response']), 'Reply sent to PickSell.');
    }
}
