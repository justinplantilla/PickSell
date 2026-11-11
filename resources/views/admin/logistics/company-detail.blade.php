@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-application-detail.css'])
@endsection
@section('title', 'Logistics company')

@section('content')
<p class="review-back"><a href="{{ route('admin.logistics.companies', $company->status === 'pending' ? [] : ['status' => $company->status]) }}" class="btn btn-outline btn-sm">← Back to logistics companies</a></p>

@if($errors->has('decision'))
    <div class="alert alert-danger" role="alert">{{ $errors->first('decision') }}</div>
@endif

<div class="review-layout">
    {{-- 1. Company and applicant --}}
    <section class="card" aria-labelledby="company-heading">
        <div class="card-header">
            <span class="card-title" id="company-heading">{{ $company->name }}</span>
            <span class="badge badge-{{ $company->status === 'rejected' ? 'disapproved' : $company->status }}">{{ ucfirst($company->status) }}</span>
        </div>
        @php
            $fields = array_filter([
                'Business name' => $company->name,
                'Business address' => collect([$address['street'], $address['barangay'], $address['municipality'], $address['province']])->filter()->join(', '),
                'Tracking prefix' => $company->tracking_prefix,
                'Applicant' => $owner?->full_name,
                'Position' => $owner?->position,
                'Sex' => $owner?->sex ? ucfirst($owner->sex) : null,
                'Birthday' => $owner?->birthday ? \Illuminate\Support\Carbon::parse($owner->birthday)->format('F d, Y').' ('.\Illuminate\Support\Carbon::parse($owner->birthday)->age.' years old)' : null,
                'Email' => $owner?->email.($owner?->email_verified_at ? ' (verified)' : ''),
                'Contact no.' => $owner?->mobile_no,
                'Terms accepted' => $company->terms_accepted_at ? $company->terms_accepted_at->format('M d, Y h:i A').' (version '.$company->terms_version.')' : null,
                'Submitted' => $company->submitted_at ? $company->submitted_at->format('M d, Y h:i A').' ('.$company->submitted_at->diffForHumans().')' : null,
                'Approved' => $company->approved_at?->format('M d, Y h:i A'),
            ]);
        @endphp
        <dl class="review-fields">
            @foreach($fields as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>

    {{-- 2. Documents --}}
    <section class="card" aria-labelledby="documents-heading">
        <div class="card-header"><span class="card-title" id="documents-heading">Submitted documents</span></div>
        @if($documents->isEmpty())
            <div class="oversight-empty"><strong>No documents uploaded</strong>{{ $company->is_default ? 'The default carrier was added by PickSell, not registered.' : '' }}</div>
        @else
            <ul class="review-documents">
                @foreach(\App\Pickrush\Models\CompanyDocument::TYPES as $type => $label)
                    @foreach($documents->get($type, collect()) as $document)
                        <li>
                            <span>
                                {{ $label }}@if($document->version > 1 || $loop->count > 1) · version {{ $document->version }}@endif
                                @if(! $loop->first)<span class="oversight-meta">(older)</span>@endif
                                <span class="oversight-meta">{{ $document->original_name }} · {{ number_format($document->size / 1024 / 1024, 2) }} MB · {{ $document->created_at->format('M d, Y') }}</span>
                            </span>
                            <a href="{{ route('admin.logistics.documents.show', $document) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Open<span class="sr-only"> {{ $label }} (opens in new tab)</span></a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    </section>

    @if($scorecard)
    {{-- Performance on PickSell parcels --}}
    <section class="card" aria-labelledby="scorecard-heading">
        <div class="card-header">
            <span class="card-title" id="scorecard-heading">Scorecard · last {{ $scorecard['days'] }} days</span>
            <a href="{{ route('admin.logistics.issues', ['company' => $company->id, 'tab' => 'all']) }}" class="btn btn-outline btn-sm">See its issues</a>
        </div>
        @php($rate = fn ($v) => $v === null ? '—' : $v.'%')
        <div class="oversight-pipeline">
            <div class="oversight-stage"><strong>{{ number_format($scorecard['delivered']) }}</strong><span>Delivered</span></div>
            <div class="oversight-stage"><strong>{{ $rate($scorecard['on_time_rate']) }}</strong><span>On time (≤ {{ \App\Services\Logistics\LogisticsScorecard::ON_TIME_DAYS }} days)</span></div>
            <div class="oversight-stage"><strong>{{ $rate($scorecard['failed_rate']) }}</strong><span>Failed / returned</span></div>
            <div class="oversight-stage {{ $scorecard['lost'] > 0 ? 'is-alert' : '' }}"><strong>{{ $rate($scorecard['lost_rate']) }}</strong><span>Lost ({{ $scorecard['lost'] }})</span></div>
            <div class="oversight-stage"><strong>{{ $scorecard['damaged'] }}</strong><span>Damaged</span></div>
            <div class="oversight-stage"><strong>{{ $scorecard['rating'] ?? '—' }}</strong><span>Delivery rating ({{ $scorecard['ratings'] }})</span></div>
            <div class="oversight-stage {{ $scorecard['overdue_issues'] > 0 ? 'is-alert' : '' }}"><strong>{{ $scorecard['open_issues'] }}</strong><span>Open issues ({{ $scorecard['overdue_issues'] }} overdue)</span></div>
            <div class="oversight-stage"><strong>₱{{ number_format($scorecard['claims'], 2) }}</strong><span>Claims charged</span></div>
        </div>
        @if($scorecard['warnings'])
            <div class="card-body">
                <div class="alert alert-warning" role="status">
                    <strong>Needs attention</strong>
                    <ul>@foreach($scorecard['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                    Suspending is your call: use the decision below if it is warranted.
                </div>
            </div>
        @endif
    </section>
    @endif

    {{-- 3. Decision --}}
    <section class="card review-decision" aria-labelledby="decision-heading">
        <div class="card-header"><span class="card-title" id="decision-heading">Decision</span></div>
        @if(! $canDecide)
            <div class="card-body"><p>Deciding on logistics companies requires the logistics management permission.</p></div>
        @elseif($company->status === 'pending')
            <form method="POST" action="{{ route('admin.logistics.companies.approve', $company) }}" class="review-form" novalidate>
                @csrf @method('PATCH')
                <p class="oversight-description">Check that the valid ID is readable and matches the applicant, and that the business / DTI permit matches the business name. Approving gives the company its tracking prefix and lets it sign in.</p>
                <div class="form-group">
                    <label class="form-label" for="decision-reason">Reason <span class="review-hint">— required to reject; sent to the company</span></label>
                    <textarea id="decision-reason" name="reason" class="form-control" rows="3" maxlength="1000" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>{{ old('reason') }}</textarea>
                    @error('reason')<span class="review-error" id="reason-error">{{ $message }}</span>@enderror
                </div>
                <div class="review-actions">
                    <button type="submit" class="btn registration-approve" data-confirm="Approve {{ $company->name }} and email the owner?">Approve</button>
                    <button type="submit" class="btn registration-disapprove" formaction="{{ route('admin.logistics.companies.reject', $company) }}" data-confirm="Reject {{ $company->name }} and email the reason?">Reject</button>
                </div>
            </form>
        @elseif($company->status === 'approved' && ! $company->is_default)
            <form method="POST" action="{{ route('admin.logistics.companies.suspend', $company) }}" class="review-form" novalidate>
                @csrf @method('PATCH')
                <p class="oversight-description">Approved. Suspending stops the company's staff from using the portal until it is reinstated.</p>
                <div class="form-group">
                    <label class="form-label" for="decision-reason">Reason <span class="review-hint">— sent to the company</span></label>
                    <textarea id="decision-reason" name="reason" class="form-control" rows="3" maxlength="1000">{{ old('reason') }}</textarea>
                    @error('reason')<span class="review-error">{{ $message }}</span>@enderror
                </div>
                <div class="review-actions">
                    <button type="submit" class="btn registration-disapprove" data-confirm="Suspend {{ $company->name }}?">Suspend</button>
                </div>
            </form>
        @elseif($company->status === 'suspended')
            <form method="POST" action="{{ route('admin.logistics.companies.reinstate', $company) }}" class="review-form">
                @csrf @method('PATCH')
                <p class="oversight-description">Suspended{{ $company->rejection_reason ? ': '.$company->rejection_reason : '.' }}</p>
                <div class="review-actions">
                    <button type="submit" class="btn registration-approve" data-confirm="Reinstate {{ $company->name }} and email the owner?">Reinstate</button>
                </div>
            </form>
        @elseif($company->status === 'rejected')
            <div class="card-body review-outcome">
                <p>Rejected. The company can correct its registration and resubmit; it then comes back to the pending queue.</p>
                @if($company->rejection_reason)<p class="review-reason"><strong>Reason:</strong> {{ $company->rejection_reason }}</p>@endif
            </div>
        @else
            <div class="card-body"><p>{{ $company->name }} is the default carrier, pre-selected for buyers at checkout.</p></div>
        @endif
    </section>

    {{-- 4. Review history --}}
    <section class="card" aria-labelledby="history-heading">
        <div class="card-header"><span class="card-title" id="history-heading">Review history</span></div>
        @if($company->reviews->isEmpty())
            <div class="oversight-empty"><strong>No reviews yet</strong></div>
        @else
            <ol class="review-history">
                @foreach($company->reviews as $review)
                    <li>
                        <div class="review-history-head">
                            <span class="badge badge-{{ in_array($review->decision, ['approved', 'reinstated'], true) ? 'approved' : ($review->decision === 'resubmitted' ? 'pending' : 'disapproved') }}">{{ \App\Pickrush\Models\CompanyReview::LABELS[$review->decision] }}</span>
                            <span>{{ $review->decision === 'resubmitted' ? ($owner?->full_name ?? 'The company') : ($review->reviewer?->full_name ?? 'Admin') }}</span>
                            <time class="oversight-meta" datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('M d, Y h:i A') }}</time>
                        </div>
                        @if($review->reason)<p class="review-reason">{{ $review->reason }}</p>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
@endsection
