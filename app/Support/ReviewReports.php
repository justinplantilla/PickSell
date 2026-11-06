<?php

namespace App\Support;

use App\Models\ProductReview;
use App\Models\ProductReviewReport;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Files a buyer's or seller's report on a product review and lets the admins know. */
final class ReviewReports
{
    public static function file(Request $request, ProductReview $review): ProductReviewReport
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ProductReviewReport::REASONS))],
            'details' => 'nullable|string|max:500',
        ], ['reason.required' => 'Choose why you are reporting this review.']);

        $report = ProductReviewReport::updateOrCreate(
            ['product_review_id' => $review->id, 'user_id' => $request->user()->id],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null, 'resolved_at' => null, 'resolved_by' => null, 'resolution' => null],
        );

        if ($report->wasRecentlyCreated && $review->openReports()->count() === 1) {
            app(AdminNotificationService::class)->notifyAdmins('reviews.reported', 'A product review was reported',
                ProductReviewReport::REASONS[$data['reason']].' · '.($review->product?->name ?? 'a product'), route('admin.reviews', [], false));
        }

        return $report;
    }
}
