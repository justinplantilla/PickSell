<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Models\LogisticsClaim;
use App\Models\LogisticsIssue;
use App\Pickrush\Models\Company;
use App\Services\Logistics\LogisticsIssueDesk;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Admin › Logistics › Issues: problems with the registered logistics companies, and their claims. */
class AdminLogisticsIssueController extends Controller
{
    public const TABS = ['open' => 'Needs action', 'escalated' => 'Waiting on company', 'overdue' => 'Overdue', 'resolved' => 'Resolved', 'all' => 'All'];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        $companyId = $request->integer('company') ?: null;
        $type = array_key_exists((string) $request->query('type'), LogisticsIssue::TYPES) ? $request->query('type') : null;
        $search = trim((string) $request->query('search', ''));

        $scoped = fn () => LogisticsIssue::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('order', fn ($o) => $o->whereLike('order_number', "%{$search}%")->orWhereLike('waybill_number', "%{$search}%"))
                ->orWhereHas('parcel', fn ($p) => $p->whereLike('tracking_no', "%{$search}%"))
                ->orWhereLike('description', "%{$search}%")));
        $inTab = fn ($query, string $key) => match ($key) {
            'open' => $query->where('status', 'open'),
            'escalated' => $query->where('status', 'escalated'),
            'overdue' => $query->overdue(),
            'resolved' => $query->where('status', 'resolved'),
            default => $query,
        };

        $issues = $inTab($scoped(), $tab)->with(['company', 'order', 'parcel', 'claim'])
            ->orderBy($tab === 'resolved' ? 'resolved_at' : 'created_at', in_array($tab, ['resolved', 'all'], true) ? 'desc' : 'asc')
            ->paginate(20)->withQueryString();

        return view('admin.logistics.issues', [
            'issues' => $issues,
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => collect(array_keys(self::TABS))->mapWithKeys(fn ($key) => [$key => $inTab($scoped(), $key)->count()]),
            'companies' => Company::whereNotNull('approved_at')->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
            'companyId' => $companyId,
            'type' => $type,
            'search' => $search,
        ]);
    }

    public function show(LogisticsIssue $issue, LogisticsIssueDesk $desk)
    {
        $issue->load(['company', 'order.buyer', 'order.seller', 'shipment', 'parcel', 'exception', 'reporter', 'resolver', 'events.user', 'events.staff', 'claim']);

        return view('admin.logistics.issue-detail', [
            'issue' => $issue,
            'canManage' => auth()->user()->can(Permission::LOGISTICS_MANAGE),
            'claimValue' => $issue->isClaimable() ? $desk->claimValue($issue) : null,
            'cap' => LogisticsClaim::cap(),
        ]);
    }

    public function evidence(LogisticsIssue $issue)
    {
        abort_unless($issue->evidence_path && Storage::disk('local')->exists($issue->evidence_path), 404);

        return Storage::disk('local')->response($issue->evidence_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function note(Request $request, LogisticsIssue $issue, LogisticsIssueDesk $desk)
    {
        $data = $request->validate(['note' => 'required|string|max:2000']);
        $desk->note($issue, $request->user(), $data['note']);

        return back()->with('success', 'Note added.');
    }

    public function escalate(Request $request, LogisticsIssue $issue, LogisticsIssueDesk $desk)
    {
        $data = $request->validate(['message' => 'required|string|min:10|max:2000'], ['message.min' => 'Tell the company what you need from them (at least 10 characters).']);

        return $this->attempt(fn () => $desk->escalate($issue, $request->user(), $data['message']), "Sent to {$issue->company->name}. It shows on their portal until they reply.");
    }

    public function resolve(Request $request, LogisticsIssue $issue, LogisticsIssueDesk $desk)
    {
        $data = $request->validate([
            'fault' => 'required|in:'.implode(',', array_keys(LogisticsIssue::FAULTS)),
            'notes' => 'required|string|min:5|max:2000',
            'claim_amount' => 'nullable|numeric|min:0|max:'.LogisticsClaim::cap(),
        ], ['claim_amount.max' => 'A claim cannot be more than the cap of ₱'.number_format(LogisticsClaim::cap(), 2).'.']);

        $claim = null;
        $response = $this->attempt(function () use ($desk, $issue, $request, $data, &$claim) {
            $claim = $desk->resolve($issue, $request->user(), $data['fault'], $data['notes'], isset($data['claim_amount']) ? (float) $data['claim_amount'] : null);
        }, 'Issue resolved.');

        return $claim ? $response->with('success', 'Issue resolved. A claim of ₱'.number_format((float) $claim->amount, 2)." is charged to {$issue->company->name}.") : $response;
    }

    public function waive(Request $request, LogisticsClaim $claim, LogisticsIssueDesk $desk)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);

        return $this->attempt(fn () => $desk->waive($claim, $request->user(), $data['reason']), 'Claim waived.');
    }

    private function attempt(callable $work, string $success)
    {
        try {
            $work();
        } catch (DomainException $e) {
            return back()->withErrors(['issue' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
