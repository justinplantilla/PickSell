<?php

namespace App\Http\Controllers;

use App\Models\CommissionRateHistory;
use App\Models\Order;
use App\Services\Admin\AdminNotificationInbox;
use App\Services\CommissionService;
use App\Services\Finance\CommissionReconciliationService;
use App\Services\Finance\FinancialSummary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    // Commission
    public function commission(
        Request $request,
        CommissionService $commission,
        CommissionReconciliationService $reconciliationService,
    ) {
        $rate = $commission->rate();
        $request->merge([
            'from' => $request->query('from', now()->startOfMonth()->toDateString()),
            'to' => $request->query('to', now()->toDateString()),
        ]);
        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = $filters['from'];
        $to = $filters['to'];

        $orders = Order::with('seller')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->latest()->get();

        $orders->each(function (Order $order) use ($commission): void {
            $order->setAttribute('calculated_commission', $commission->commissionForOrder($order));
            $order->setAttribute('effective_commission_rate', $commission->rateForOrder($order));
        });

        $hasFinancialTransactions = Schema::hasTable('financial_transactions');
        $hasCommissionRateHistories = Schema::hasTable('commission_rate_histories');
        $missingTables = [];
        if (! $hasFinancialTransactions) {
            $missingTables[] = 'financial_transactions';
        }
        if (! $hasCommissionRateHistories) {
            $missingTables[] = 'commission_rate_histories';
        }
        $migrationWarning = null;
        $reconciliation = null;
        if ($hasFinancialTransactions) {
            $summary = app(FinancialSummary::class)->forPeriod(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay());
            $totalSales = $summary['gross_sales'];
            $totalCommission = $summary['commission'];
            $reconciliation = $reconciliationService->reconcile($orders);
        } else {
            $totalSales = $commission->sum($orders->pluck('amount'));
            $totalCommission = $commission->sum($orders->pluck('calculated_commission'));
        }
        $rateHistory = $hasCommissionRateHistories
            ? CommissionRateHistory::with('changedBy')->latest('effective_from')->limit(20)->get()
            : collect();
        if ($missingTables !== []) {
            Log::warning('Admin commission page is missing required finance migrations.', [
                'missing_tables' => $missingTables,
            ]);
            $warnings = [];
            if (in_array('financial_transactions', $missingTables, true)) {
                $warnings[] = 'Financial ledger migration is not applied. Totals use completed order snapshots and may not include refunds or adjustments.';
            }
            if (in_array('commission_rate_histories', $missingTables, true)) {
                $warnings[] = 'Commission rate history is unavailable until its migration is applied.';
            }
            $migrationWarning = implode(' ', $warnings);
        }

        return view('admin.commission', compact('orders', 'rate', 'totalSales', 'totalCommission', 'from', 'to', 'rateHistory', 'migrationWarning', 'reconciliation'));
    }

    // Notifications
    public function notificationCenter(Request $request, AdminNotificationInbox $inbox)
    {
        $filter = AdminNotificationInbox::normalizeFilter($request->query('filter'));
        $notifications = $inbox->query($request->user(), $filter)->latest()->paginate(25)->withQueryString();
        $sections = $inbox->present($notifications);
        $filters = AdminNotificationInbox::FILTERS;
        $unreadCounts = $inbox->unreadCounts($request->user());

        return view('admin.notifications', compact('notifications', 'sections', 'filter', 'filters', 'unreadCounts'));
    }

    /** JSON feed for the header bell. */
    public function notifications()
    {
        $notifications = auth()->user()->notifications()->latest()->take(20)->get();
        $unreadCount = auth()->user()->unreadNotifications()->count();
        $notifications->each(function ($notification): void {
            $data = $notification->data;
            if (is_string($data)) {
                $data = json_decode($data, true) ?: [];
            }
            $data['url'] = AdminNotificationInbox::safeUrl($data['url'] ?? null);
            $notification->setAttribute('data', $data);
        });

        return response()->json($notifications)->header('X-Unread-Count', $unreadCount);
    }

    /** Marks all unread notifications read, or only those in the given filter tab. */
    public function markNotificationsRead(Request $request, AdminNotificationInbox $inbox)
    {
        $inbox->markRead($request->user(), AdminNotificationInbox::normalizeFilter($request->input('filter')));

        return response()->json([
            'success' => true,
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Marks one notification read; `ids` also clears the repeats merged into the same row. */
    public function markNotificationRead(Request $request, string $notification)
    {
        $ids = collect($request->input('ids', []))->filter(fn ($id) => is_string($id))->push($notification)->unique();
        $records = $request->user()->notifications()->whereKey($ids->all())->get();
        abort_unless($records->contains('id', $notification), 404);
        $records->each->markAsRead();

        return response()->json(['success' => true]);
    }
}
