{{-- Report a product review to PickSell's moderators. $action is the report route; $id keeps field ids unique. --}}
<details class="review-report">
    <summary>Report</summary>
    <form method="POST" action="{{ $action }}" class="review-report-form" data-no-confirm>
        @csrf
        <label class="form-label" for="report-reason-{{ $id }}">What's wrong with this review?</label>
        <select id="report-reason-{{ $id }}" name="reason" class="form-control" required>
            <option value="">Choose a reason</option>
            @foreach(\App\Models\ProductReviewReport::REASONS as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <label class="form-label" for="report-details-{{ $id }}">Details (optional)</label>
        <textarea id="report-details-{{ $id }}" name="details" class="form-control" rows="2" maxlength="500"></textarea>
        <button type="submit" class="btn btn-outline btn-sm">Send report</button>
    </form>
</details>
