@extends('admin.layout')
@section('styles')
@vite('resources/css/components/review-moderation.css')
@endsection
@section('title', 'Review Moderation')

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Product reviews</span></div>
    <div class="card-body">
        <nav class="moderation-tabs" aria-label="Review filters">
            @foreach(\App\Http\Controllers\AdminReviewController::TABS as $key => $label)
            <a href="{{ route('admin.reviews', ['tab' => $key]) }}" class="{{ $tab === $key ? 'is-active' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}@isset($counts[$key]) <span>{{ $counts[$key] }}</span>@endisset</a>
            @endforeach
        </nav>

        @forelse($reviews as $review)
        <article class="moderation-row {{ $review->hidden_at ? 'is-hidden' : '' }}">
            <div class="moderation-head">
                <div>
                    <strong>{{ $review->product?->name ?? 'Removed product' }}</strong>
                    <span class="moderation-muted">· {{ $review->product?->seller?->business_name ?? $review->product?->seller?->full_name }}</span>
                </div>
                <span class="moderation-stars" aria-label="{{ $review->rating }} out of 5">{{ str_repeat('★', $review->rating) }}<span>{{ str_repeat('★', 5 - $review->rating) }}</span></span>
            </div>
            <p class="moderation-body">{{ $review->body ?: 'No written review.' }}</p>
            @if($review->photos->isNotEmpty())
            <div class="moderation-photos">@foreach($review->photos as $photo)<a href="{{ Storage::url($photo->path) }}" target="_blank" rel="noopener"><img src="{{ Storage::url($photo->path) }}" alt="Review photo"></a>@endforeach</div>
            @endif
            <p class="moderation-muted">By {{ $review->buyer?->full_name ?? 'a deleted account' }} · {{ $review->created_at->format('M d, Y') }}</p>

            @if($review->hidden_at)
            <p class="moderation-flag">Hidden {{ $review->hidden_at->format('M d, Y') }}: {{ $review->hidden_reason }}</p>
            @endif
            @if($review->openReports->isNotEmpty())
            <ul class="moderation-reports">
                @foreach($review->openReports as $report)
                <li><strong>{{ \App\Models\ProductReviewReport::REASONS[$report->reason] ?? $report->reason }}</strong> · {{ $report->reporter?->role === 'seller' ? 'Seller' : 'Buyer' }} {{ $report->reporter?->first_name }}@if($report->details) · “{{ $report->details }}”@endif</li>
                @endforeach
            </ul>
            @endif

            @can(\App\Auth\Permission::PRODUCTS_MODERATE)
            <div class="moderation-actions">
                @if($review->hidden_at)
                <form method="POST" action="{{ route('admin.reviews.restore', $review) }}">@csrf<button type="submit" class="btn btn-outline btn-sm">Restore review</button></form>
                @else
                <form method="POST" action="{{ route('admin.reviews.hide', $review) }}" class="moderation-hide">
                    @csrf
                    <label class="sr-only" for="hide-reason-{{ $review->id }}">Reason for hiding</label>
                    <input type="text" id="hide-reason-{{ $review->id }}" name="reason" class="form-control" maxlength="255" required placeholder="Reason, e.g. abusive language" value="{{ $review->openReports->first() ? (\App\Models\ProductReviewReport::REASONS[$review->openReports->first()->reason] ?? '') : '' }}">
                    <button type="submit" class="btn btn-danger btn-sm">Hide review</button>
                </form>
                @if($review->openReports->isNotEmpty())
                <form method="POST" action="{{ route('admin.reviews.dismiss', $review) }}">@csrf<button type="submit" class="btn btn-outline btn-sm">Keep it, dismiss reports</button></form>
                @endif
                @endif
            </div>
            @endcan
        </article>
        @empty
        <p class="moderation-muted">{{ $tab === 'reported' ? 'No reported reviews. Nice and quiet.' : 'No reviews here.' }}</p>
        @endforelse
        {{ $reviews->links() }}
    </div>
</div>
@endsection
