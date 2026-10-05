<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Mail\RegistrationApprovedMail;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\PlatformSetting;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Admin\ComplaintResolution;
use App\Services\Admin\PlatformSettingsService;
use App\Services\Finance\FinancialSummary;
use App\Services\PlatformCommission;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Gate;

class AdminController extends Controller
{
    // Complaints
    public function complaints(Request $request)
    {
        $status = $request->get('status', 'all');
        $query  = Complaint::with(['filer', 'against']);
        if ($status !== 'all') $query->where('status', $status);
        $complaints = $query->latest()->paginate(15);
        $users = User::whereIn('role', ['buyer', 'seller', 'courier'])->where('status', 'approved')->get();
        return view('admin.complaints', compact('complaints', 'status', 'users'));
    }

    public function showComplaint(Complaint $complaint)
    {
        Gate::authorize('view', $complaint);
        $complaint->load(['filer', 'against']);
        return view('admin.complaint-detail', compact('complaint'));
    }

    public function updateComplaint(Request $request, Complaint $complaint, ComplaintResolution $resolution)
    {
        Gate::authorize('update', $complaint);
        $data = $request->validate([
            'status'      => 'required|in:open,under_review,resolved,dismissed',
            'admin_notes' => 'nullable|string|max:2000',
        ]);
        $resolution->update($complaint, $data['status'], $data['admin_notes'] ?? null);

        return back()->with('success', 'Complaint updated successfully.');
    }

    // Commission
    public function commission(Request $request, PlatformCommission $platformCommission)
    {
        $rate = $platformCommission->rate();
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to', now()->format('Y-m-d'));

        $orders = Order::with('seller')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->latest()->get();

        $orders->each(function (Order $order) use ($platformCommission): void {
            $order->setAttribute('calculated_commission', $platformCommission->deduction((float) $order->amount));
        });

        // Totals come from the Finance layer so they match the dashboard and Financial Reports.
        $summary = app(FinancialSummary::class)->forPeriod(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay());
        $totalSales      = $summary['gross_sales'];
        $totalCommission = $summary['commission'];

        return view('admin.commission', compact('orders', 'rate', 'totalSales', 'totalCommission', 'from', 'to'));
    }

    // Reports
    public function reports(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to', now()->format('Y-m-d'));
        $data = $this->getReportData($from, $to);
        return view('admin.reports', compact('data', 'from', 'to'));
    }

    public function exportPdf(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to', now()->format('Y-m-d'));
        $type = $request->get('type', 'sales');
        $data = $this->getReportData($from, $to);
        $pdf  = Pdf::loadView('admin.pdf.report', compact('data', 'from', 'to', 'type'))
                   ->setPaper('a4', 'portrait');
        return $pdf->download("picksell-{$type}-report-{$from}-to-{$to}.pdf");
    }

    private function getReportData($from, $to): array
    {
        $platformCommission = app(PlatformCommission::class);
        $orders = Order::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->with('seller')->get();

        $orders->each(function (Order $order) use ($platformCommission): void {
            $order->setAttribute('calculated_commission', $platformCommission->deduction((float) $order->amount));
        });

        $topSellers = $orders
            ->groupBy('seller_id')
            ->map(fn($g) => [
                'name'       => $g->first()->seller->full_name ?? '—',
                'sales'      => $g->sum('amount'),
                'orders'     => $g->count(),
                'commission' => $g->sum('calculated_commission'),
            ])->sortByDesc('sales')->take(5)->values()->toArray();

        // Weekly breakdown for current period
        $weeklySales = [];
        $weeklyCommissions = [];
        $weekLabels  = [];
        $start = \Carbon\Carbon::parse($from);
        $end   = \Carbon\Carbon::parse($to);
        $week  = 1;
        while ($start->lte($end)) {
            $weekStart = $start->copy()->startOfDay();
            $weekEnd = $start->copy()->addDays(6)->endOfDay()->min($end->copy()->endOfDay());
            $weeklyOrders = $orders->filter(fn (Order $order): bool => $order->created_at->betweenIncluded($weekStart, $weekEnd));
            $weeklySales[] = $weeklyOrders->sum('amount');
            $weeklyCommissions[] = $weeklyOrders->sum('calculated_commission');
            $weekLabels[] = 'Week ' . $week++;
            $start->addDays(7);
        }

        $summary = app(FinancialSummary::class)->forPeriod(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay());

        return [
            'total_sales'      => $summary['gross_sales'],
            'total_orders'     => $summary['completed_orders'],
            'total_commission' => $summary['commission'],
            'total_net_earnings' => $summary['net_to_sellers'],
            'commission_rate'  => $summary['commission_rate'],
            'new_buyers'       => User::where('role', 'buyer')->whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
            'new_sellers'      => User::where('role', 'seller')->whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
            'monthly_sales'    => $weeklySales ?: [0],
            'monthly_commissions' => $weeklyCommissions ?: [0],
            'months'           => $weekLabels ?: ['No Data'],
            'top_sellers'      => $topSellers,
        ];
    }


    // Settings
    public function settings()
    {
        $announcements = Announcement::latest()->get();
        $tos      = PlatformSetting::get('terms_of_service');
        $privacy  = PlatformSetting::get('privacy_policy');
        $platformName = PlatformSetting::get('platform_name', 'PickSell');
        $supportEmail = PlatformSetting::get('support_email', 'support@picksell.ph');
        $commissionRate = PlatformSetting::get('commission_rate', '10');
        $maxFileUploadMb = PlatformSetting::get('max_file_upload_mb', '5');

        return view('admin.settings', compact(
            'announcements',
            'tos',
            'privacy',
            'platformName',
            'supportEmail',
            'commissionRate',
            'maxFileUploadMb'
        ));
    }

    public function saveSettings(Request $request, PlatformSettingsService $settings, PlatformCommission $platformCommission)
    {
        if ($request->has('announcement_title')) {
            $request->validate([
                'announcement_title' => 'required|string|max:255',
                'announcement'       => 'required|string|max:1000',
                'audience'           => 'required|in:all,buyer,seller,courier',
            ]);
            $settings->postAnnouncement([
                'title'    => $request->announcement_title,
                'message'  => $request->announcement,
                'audience' => $request->audience,
            ]);
            return back()->with('success', 'Announcement posted successfully.');
        }

        if ($request->hasAny(['platform_name', 'support_email', 'commission_rate', 'max_file_upload_mb'])) {
            $request->validate([
                'platform_name' => 'nullable|string|max:255',
                'support_email' => 'nullable|email|max:255',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'max_file_upload_mb' => 'nullable|integer|min:1|max:500',
            ]);

            // The commission rate lives on this form but belongs to manage-commission. A missing
            // field (e.g. disabled for an admin without that permission) keeps the current rate.
            if ($request->filled('commission_rate') && (float) $request->input('commission_rate') !== $platformCommission->rate()) {
                Gate::authorize(Permission::COMMISSION_MANAGE);
                $settings->save(['commission_rate' => (string) (float) $request->input('commission_rate')], 'settings.commission_rate_changed', Permission::COMMISSION_MANAGE);
            }
            $settings->save([
                'platform_name' => (string) $request->input('platform_name', 'PickSell'),
                'support_email' => (string) $request->input('support_email', 'support@picksell.ph'),
                'max_file_upload_mb' => (string) $request->input('max_file_upload_mb', '5'),
            ], 'settings.general_updated');

            return back()->with('success', 'General settings saved successfully.');
        }

        // Save policies
        $request->validate([
            'policy'  => 'nullable|string',
            'privacy' => 'nullable|string',
        ]);
        $settings->save([
            'terms_of_service' => $request->policy ?? '',
            'privacy_policy' => $request->privacy ?? '',
        ], 'settings.policies_updated');
        return back()->with('success', 'Policies saved successfully.');
    }

    public function toggleAnnouncement(Announcement $announcement, PlatformSettingsService $settings)
    {
        $settings->toggleAnnouncement($announcement);
        return back()->with('success', 'Announcement updated.');
    }

    public function deleteAnnouncement(Announcement $announcement, PlatformSettingsService $settings)
    {
        $settings->deleteAnnouncement($announcement);
        return back()->with('success', 'Announcement deleted.');
    }

    // Chat
    public function chat(Request $request)
    {
        $admin = auth()->user();

        $users = User::whereIn('role', ['buyer', 'seller', 'courier', 'logistics'])
            ->where('status', 'approved')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $activeUserId = $request->get('user');
        $activeUser   = $activeUserId ? User::find($activeUserId) : null;
        $messages     = [];

        if ($activeUser) {
            Message::where('sender_id', $activeUserId)
                ->where('receiver_id', $admin->id)
                ->update(['read' => true]);

            $messages = Message::where(function ($q) use ($admin, $activeUserId) {
                $q->where('sender_id', $admin->id)->where('receiver_id', $activeUserId);
            })->orWhere(function ($q) use ($admin, $activeUserId) {
                $q->where('sender_id', $activeUserId)->where('receiver_id', $admin->id);
            })->orderBy('created_at')->get();
        }

        $users = $users->map(function ($u) use ($admin) {
            $u->unread = Message::where('sender_id', $u->id)
                ->where('receiver_id', $admin->id)
                ->where('read', false)->count();
            return $u;
        });

        return view('admin.chat', compact('users', 'activeUser', 'messages'));
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'body'        => 'required|string|max:2000',
        ]);
        Message::create([
            'sender_id'   => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'body'        => $request->body,
            'read'        => false,
        ]);

        return redirect()->route('admin.chat', ['user' => $request->receiver_id]);
    }

    // Notifications
    public function notificationCenter()
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(25);
        $notifications->getCollection()->each(function ($notification): void {
            $data = $notification->data;
            if (is_string($data)) {
                $data = json_decode($data, true) ?: [];
            }
            $notification->setAttribute('message', $data['message'] ?? 'Notification');
        });

        return view('admin.notifications', compact('notifications'));
    }

    /** JSON feed for the header bell. */
    public function notifications()
    {
        $notifications = auth()->user()->notifications()->latest()->take(20)->get();
        $unreadCount = auth()->user()->unreadNotifications()->count();
        return response()->json($notifications)->header('X-Unread-Count', $unreadCount);
    }

    public function markNotificationsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }

    // Account Management
    public function account()
    {
        return view('admin.account');
    }

    public function updateAccount(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email,' . auth()->id(),
            'contact_no' => 'required|string|max:20',
        ]);
        auth()->user()->update($request->only('first_name', 'last_name', 'email', 'contact_no'));
        return back()->with('success', 'Account updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);
        if (!\Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        auth()->user()->update(['password' => \Hash::make($request->password)]);
        return back()->with('success', 'Password changed successfully.');
    }

    // Helper: store a simple DB notification for admin
    private function notifyAdmin(string $message): void
    {
        $admin = auth()->user();
        $admin->notifications()->create([
            'id'   => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\AdminActivity',
            'data' => json_encode(['message' => $message]),
        ]);
    }
}
