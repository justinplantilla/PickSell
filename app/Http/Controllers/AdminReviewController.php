<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Models\ProductReview;
use App\Models\ProductReviewReport;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Review moderation: reviews buyers or sellers reported, and ones already hidden. Hiding takes a
 * review off the product page and out of its rating; it can be restored.
 */
class AdminReviewController extends Controller
{
    public const TABS = ['reported' => 'Reported', 'hidden' => 'Hidden', 'all' => 'All reviews'];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'reported';
        $reviews = ProductReview::query()
            ->with(['product.seller', 'buyer', 'photos', 'openReports.reporter'])->withCount('openReports')
            ->when($tab === 'reported', fn ($q) => $q->whereHas('openReports')->orderByDesc('open_reports_count'))
            ->when($tab === 'hidden', fn ($q) => $q->whereNotNull('hidden_at'))
            ->latest()->paginate(20)->withQueryString();
        $counts = [
            'reported' => ProductReview::whereHas('openReports')->count(),
            'hidden' => ProductReview::whereNotNull('hidden_at')->count(),
        ];

        return view('admin.reviews', compact('reviews', 'tab', 'counts'));
    }

    public function hide(Request $request, ProductReview $review, AuditLogger $audit)
    {
        $data = $request->validate(['reason' => 'required|string|max:255'], ['reason.required' => 'Say why the review is hidden.']);
        DB::transaction(function () use ($request, $review, $data, $audit) {
            $review->update(['hidden_at' => now(), 'hidden_by' => $request->user()->id, 'hidden_reason' => $data['reason']]);
            $this->resolveReports($request, $review, 'hidden');
            $audit->record('reviews.hidden', $review, metadata: ['reason' => $data['reason']], permission: Permission::PRODUCTS_MODERATE);
        });

        return back()->with('success', 'Review hidden. It no longer shows on the product page or counts toward its rating.');
    }

    public function restore(Request $request, ProductReview $review, AuditLogger $audit)
    {
        DB::transaction(function () use ($review, $audit) {
            $review->update(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null]);
            $audit->record('reviews.restored', $review, permission: Permission::PRODUCTS_MODERATE);
        });

        return back()->with('success', 'Review restored.');
    }

    /** The review stays up; its open reports are closed. */
    public function dismiss(Request $request, ProductReview $review, AuditLogger $audit)
    {
        DB::transaction(function () use ($request, $review, $audit) {
            $this->resolveReports($request, $review, 'kept');
            $audit->record('reviews.reports_dismissed', $review, permission: Permission::PRODUCTS_MODERATE);
        });

        return back()->with('success', 'Reports dismissed. The review stays up.');
    }

    private function resolveReports(Request $request, ProductReview $review, string $resolution): void
    {
        ProductReviewReport::where('product_review_id', $review->id)->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolution' => $resolution]);
    }
}
