<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ApproveRegistrationRequest;
use App\Http\Requests\Admin\DisapproveRegistrationRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Admin\RegistrationReviewService;
use App\Support\RegistrationChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminRegistrationController extends Controller
{
    public const DATE_FILTERS = [
        'today' => 'Submitted today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'older_7d' => 'Waiting over 7 days',
    ];

    public function index(Request $request)
    {
        $role = in_array($request->query('role'), UserPolicy::MANAGED_ROLES, true) ? $request->query('role') : 'all';
        $status = in_array($request->query('status'), ['pending', 'approved', 'disapproved', 'all'], true) ? $request->query('status') : 'pending';
        $date = array_key_exists($request->query('date'), self::DATE_FILTERS) ? $request->query('date') : 'any';
        $search = trim((string) $request->query('search', ''));

        $query = User::whereIn('role', UserPolicy::MANAGED_ROLES)->with('latestRegistrationReview.reviewer');
        if ($role !== 'all') {
            $query->where('role', $role);
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        match ($date) {
            'today' => $query->whereDate('created_at', today()),
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            'older_7d' => $query->where('created_at', '<', now()->subDays(7)),
            default => null,
        };
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%"));
        }
        // The pending queue is first-come, first-served; decided lists show the newest first.
        $status === 'pending' ? $query->oldest() : $query->latest();

        return view('admin.registrations', [
            'users' => $query->paginate(15)->withQueryString(),
            'role' => $role,
            'status' => $status,
            'date' => $date,
            'search' => $search,
            'dateFilters' => self::DATE_FILTERS,
        ]);
    }

    public function show(User $user)
    {
        Gate::authorize('viewRegistration', $user);

        return view('admin.application-detail', [
            'user' => $user,
            'checklist' => RegistrationChecklist::for($user),
            'documents' => RegistrationChecklist::documents($user),
            'reviews' => $user->registrationReviews()->with('reviewer')->get(),
            'auditHistory' => AuditLog::with('actor')
                ->where('subject_type', $user->getMorphClass())
                ->where('subject_id', $user->id)
                ->latest('id')->take(50)->get(),
        ]);
    }

    public function approve(ApproveRegistrationRequest $request, User $user, RegistrationReviewService $reviews)
    {
        $reviews->approve($user, $request->user(), $request->confirmedChecklist(), $request->validated('reason'));

        return $this->decided($reviews, "Application of {$user->full_name} approved.");
    }

    public function disapprove(DisapproveRegistrationRequest $request, User $user, RegistrationReviewService $reviews)
    {
        $reviews->disapprove($user, $request->user(), $request->validated('reason'), $request->confirmedChecklist());

        return $this->decided($reviews, "Application of {$user->full_name} disapproved.");
    }

    private function decided(RegistrationReviewService $reviews, string $message)
    {
        return $reviews->notified
            ? back()->with('success', "{$message} The applicant has been notified.")
            : back()->with('success', $message)->with('warning', 'The decision was saved, but the notification email could not be sent. Check the mail settings.');
    }
}
