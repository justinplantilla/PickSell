<?php

namespace App\Http\Controllers;

use App\Pickrush\Models\Company;
use App\Pickrush\Models\CompanySettlement;
use App\Pickrush\Services\Hq\PicksellSettlement;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * What the logistics companies pay PickSell for delivered parcels: settlements to check against
 * PickSell's bank statement (confirm, or dispute with what the bank shows), disputes waiting for a
 * company's answer, and each company's balance and deadline.
 */
class AdminLogisticsSettlementController extends Controller
{
    public function index(PicksellSettlement $picksell)
    {
        return view('admin.logistics.settlements', [
            // Newest first; a contested settlement counts from when the company contested it.
            'pending' => CompanySettlement::with('company', 'settles', 'submitter')->where('status', 'submitted')
                ->orderByRaw('coalesce(contested_at, created_at) desc')->orderByDesc('id')->get(),
            'disputes' => CompanySettlement::with('company', 'topUps')->where('status', 'disputed')->whereNull('settles_id')->latest('confirmed_at')->orderByDesc('id')->get(),
            'recent' => CompanySettlement::with('company', 'confirmer')->where('status', '!=', 'submitted')->latest('confirmed_at')->orderByDesc('id')->limit(20)->get(),
            'companies' => Company::whereNotNull('approved_at')->orderByDesc('is_default')->orderBy('name')->get()
                ->map(fn (Company $company) => ['company' => $company, 'owed' => $picksell->owed($company->id), 'deadline' => $picksell->deadline($company->id)]),
        ]);
    }

    public function review(Request $request, CompanySettlement $settlement, PicksellSettlement $picksell)
    {
        $data = $request->validate([
            'decision' => 'required|in:confirm,dispute',
            'bank_amount' => 'required_if:decision,dispute|nullable|numeric|min:0|max:100000000',
            'note' => 'nullable|string|max:1000',
        ], ['bank_amount.required_if' => 'Enter the amount the bank statement shows for this deposit.']);
        $confirming = $data['decision'] === 'confirm';

        try {
            $picksell->confirm($settlement, $request->user(), $confirming, $data['note'] ?? null, $confirming ? null : (float) $data['bank_amount']);
        } catch (DomainException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return back()->with('success', $confirming ? 'Settlement confirmed.' : 'Settlement disputed; the company will see it.');
    }

    public function slip(CompanySettlement $settlement)
    {
        return $this->file($settlement->slip_path);
    }

    public function evidence(CompanySettlement $settlement)
    {
        return $this->file($settlement->contest_path);
    }

    private function file(?string $path)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
