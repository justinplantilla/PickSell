<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Models\ComplianceCase;
use App\Models\OffPlatformFlag;
use App\Models\User;
use App\Services\OffPlatform\OffPlatformMonitor;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin › Off-Platform Monitoring: the flags queue, sellers by risk, and each seller's review. */
class AdminOffPlatformController extends Controller
{
    public const TABS = ['queue' => 'Needs review', 'sellers' => 'Sellers by risk', 'reviewed' => 'Reviewed'];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'queue';
        $kind = array_key_exists((string) $request->query('kind'), OffPlatformFlag::KINDS) ? $request->query('kind') : null;

        $flags = OffPlatformFlag::query()->with(['seller', 'buyer', 'product', 'order'])
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->when($tab === 'reviewed', fn ($q) => $q->where('status', '!=', 'open')->latest('reviewed_at'), fn ($q) => $q->open()->orderByDesc('weight')->oldest())
            ->paginate(25)->withQueryString();

        $sellers = $tab === 'sellers'
            ? OffPlatformFlag::query()->counting()
                ->selectRaw('seller_id, sum(weight) as score, count(*) as flags, sum(case when status = \'open\' then 1 else 0 end) as open_flags, max(created_at) as last_flag')
                ->groupBy('seller_id')->orderByDesc('score')->with('seller')->limit(50)->get()
            : collect();

        return view('admin.off-platform.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'kind' => $kind,
            'flags' => $flags,
            'sellers' => $sellers,
            'openCount' => OffPlatformFlag::open()->count(),
        ]);
    }

    public function seller(User $user, OffPlatformMonitor $monitor)
    {
        abort_unless($user->role === 'seller', 404);

        return view('admin.off-platform.seller', [
            'seller' => $user,
            'risk' => $monitor->risk($user),
            'flags' => OffPlatformFlag::where('seller_id', $user->id)->with(['buyer', 'product', 'order', 'message', 'reviewer'])->latest()->paginate(30),
            'canManage' => auth()->user()->can(Permission::SELLER_COMPLIANCE_MANAGE),
            'severities' => array_keys(ComplianceCase::SEVERITIES),
        ]);
    }

    public function dismiss(Request $request, OffPlatformFlag $flag, OffPlatformMonitor $monitor)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $monitor->dismiss($flag, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Flag dismissed.');
    }

    public function restoreListing(Request $request, OffPlatformFlag $flag, OffPlatformMonitor $monitor)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->attempt(fn () => $monitor->restoreListing($flag, $request->user(), $data['note'] ?? null), 'Listing approved and live again.');
    }

    public function act(Request $request, User $user, OffPlatformMonitor $monitor)
    {
        abort_unless($user->role === 'seller', 404);
        $data = $request->validate([
            'action' => 'required|in:warn,case,suspend',
            'message' => 'required|string|min:20|max:5000',
            'severity' => 'nullable|required_if:action,case|in:'.implode(',', array_keys(ComplianceCase::SEVERITIES)),
        ], ['message.min' => 'Write at least 20 characters; the seller reads this.']);

        return $this->attempt(fn () => DB::transaction(fn () => match ($data['action']) {
            'warn' => $monitor->warn($user, $request->user(), $data['message']),
            'case' => $monitor->openCase($user, $request->user(), $data['message'], $data['severity']),
            'suspend' => $monitor->suspend($user, $request->user(), $data['message']),
        }), match ($data['action']) {
            'warn' => 'Seller warned. This counts as a strike.',
            'case' => 'Compliance case opened.',
            'suspend' => 'Seller suspended.',
        });
    }

    private function attempt(callable $work, string $success)
    {
        try {
            $work();
        } catch (DomainException $e) {
            return back()->withErrors(['off_platform' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
