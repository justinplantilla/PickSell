@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-application-detail.css'])
@endsection
@section('title', 'Review Application')

@section('content')
@php
    $canDecide = auth()->user()->can('approveRegistration', $user);
    $latest = $reviews->first();
@endphp
<p class="review-back"><a href="{{ route('admin.registrations') }}" class="btn btn-outline btn-sm">← Back to registrations</a></p>

<div class="review-layout">
    {{-- 1. Applicant information --}}
    <section class="card" aria-labelledby="applicant-heading">
        <div class="card-header">
            <span class="card-title" id="applicant-heading">Applicant information</span>
            <span>
                <span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                <span class="badge badge-{{ $user->status }}">{{ ucfirst($user->status) }}</span>
            </span>
        </div>
        @php
            $fields = array_filter([
                'Full name' => $user->full_name,
                'Sex' => $user->sex,
                'Birthday' => $user->birthday ? $user->birthday->format('F d, Y') . ' (' . $user->age . ' years old)' : null,
                'Email' => $user->email,
                'Contact no.' => $user->contact_no,
                'Address' => collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->join(', '),
                'Business name' => $user->business_name,
                'Line of business' => $user->line_of_business,
                'Provider type' => $user->provider_type ? ucfirst($user->provider_type) : null,
                'Vehicle' => $user->vehicle_type ? $user->vehicle_type . ($user->plate_number ? ' · ' . $user->plate_number : '') : null,
                'Delivery area' => $user->delivery_area,
                'Submitted' => $user->created_at->format('M d, Y h:i A') . ' (' . $user->created_at->diffForHumans() . ')',
            ]);
        @endphp
        <dl class="review-fields">
            @foreach($fields as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>

    {{-- 2. Submitted requirements --}}
    <section class="card" aria-labelledby="requirements-heading">
        <div class="card-header"><span class="card-title" id="requirements-heading">Submitted requirements</span></div>
        @if($documents)
            <ul class="review-documents">
                @foreach($documents as $document)
                    <li>
                        <span>{{ $document['label'] }}</span>
                        <a href="{{ asset('storage/' . $document['path']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Open<span class="sr-only"> {{ $document['label'] }} (opens in new tab)</span></a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="oversight-empty"><strong>No documents uploaded</strong></div>
        @endif
    </section>

    {{-- 3–5. Verification checklist, admin decision, decision reason --}}
    <section class="card review-decision" aria-labelledby="decision-heading">
        <div class="card-header"><span class="card-title" id="decision-heading">{{ $canDecide ? 'Verification & decision' : 'Decision' }}</span></div>
        @if($canDecide)
            <form method="POST" action="{{ route('admin.registrations.approve', $user) }}" class="review-form" novalidate>
                @csrf @method('PATCH')
                <fieldset class="review-checklist">
                    <legend>Verification checklist</legend>
                    <p class="oversight-description">Every item must be confirmed to approve. Unconfirmed items are recorded with a disapproval.</p>
                    @foreach($checklist as $key => $item)
                        <label class="review-check {{ $item['available'] ? '' : 'is-unavailable' }}">
                            <input type="checkbox" name="checklist[{{ $key }}]" value="1" @checked(old("checklist.{$key}")) @disabled(! $item['available'])
                                @error("checklist.{$key}") aria-invalid="true" aria-describedby="check-error-{{ $key }}" @enderror>
                            <span>
                                {{ $item['label'] }}
                                @unless($item['available'])<span class="review-missing">Document not uploaded — cannot be confirmed</span>@endunless
                                @error("checklist.{$key}")<span class="review-error" id="check-error-{{ $key }}">{{ $message }}</span>@enderror
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                <div class="form-group">
                    <label class="form-label" for="decision-reason">Decision reason <span class="review-hint">— required to disapprove; sent to the applicant</span></label>
                    <textarea id="decision-reason" name="reason" class="form-control" rows="3" maxlength="1000" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>{{ old('reason') }}</textarea>
                    @error('reason')<span class="review-error" id="reason-error">{{ $message }}</span>@enderror
                </div>

                <div class="review-actions">
                    <button type="submit" class="btn registration-approve" onclick="return confirm('Approve this application and notify the applicant?')">Approve</button>
                    <button type="submit" class="btn registration-disapprove" formaction="{{ route('admin.registrations.disapprove', $user) }}" onclick="return confirm('Disapprove this application and send the reason to the applicant?')">Disapprove</button>
                </div>
            </form>
        @elseif($user->status === 'pending' && $user->role === 'courier')
            <div class="card-body"><p>Courier applications are reviewed in the Logistics portal.</p></div>
        @elseif($user->status === 'pending')
            <div class="card-body"><p>This application is pending. Deciding it requires the registrations management permission.</p></div>
        @elseif($latest)
            <div class="card-body review-outcome">
                <p><span class="badge badge-{{ $latest->decision }}">{{ ucfirst($latest->decision) }}</span> by {{ $latest->reviewer?->full_name ?? 'Admin' }} on {{ $latest->reviewed_at->format('M d, Y h:i A') }}</p>
                @if($latest->reason)<p class="review-reason"><strong>Reason:</strong> {{ $latest->reason }}</p>@endif
            </div>
        @else
            <div class="card-body"><p>Status: <span class="badge badge-{{ $user->status }}">{{ ucfirst($user->status) }}</span>. This decision was made before review history was recorded.</p></div>
        @endif
    </section>

    {{-- 6. Previous review history --}}
    <section class="card" aria-labelledby="history-heading">
        <div class="card-header"><span class="card-title" id="history-heading">Review history</span></div>
        @if($reviews->isEmpty())
            <div class="oversight-empty"><strong>No reviews yet</strong></div>
        @else
            <ol class="review-history">
                @foreach($reviews as $review)
                    <li>
                        <div class="review-history-head">
                            <span class="badge badge-{{ $review->decision }}">{{ ucfirst($review->decision) }}</span>
                            <span>{{ $review->reviewer?->full_name ?? 'Admin' }}</span>
                            <time class="oversight-meta" datetime="{{ $review->reviewed_at->toIso8601String() }}">{{ $review->reviewed_at->format('M d, Y h:i A') }}</time>
                        </div>
                        @if($review->reason)<p class="review-reason">{{ $review->reason }}</p>@endif
                        @php $checks = collect($review->verification_snapshot['checklist'] ?? []); @endphp
                        @if($checks->isNotEmpty())
                            <p class="oversight-meta">Checklist: {{ $checks->where('confirmed', true)->count() }} of {{ $checks->count() }} confirmed
                                @if($checks->where('confirmed', false)->isNotEmpty()) · not confirmed: {{ $checks->where('confirmed', false)->pluck('label')->join('; ') }}@endif
                            </p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- 7. Audit history --}}
    <section class="card" aria-labelledby="audit-heading">
        <div class="card-header">
            <span class="card-title" id="audit-heading">Audit history</span>
            @can(\App\Auth\Permission::AUDIT_VIEW)<a href="{{ route('admin.audit') }}" class="oversight-description">All audit logs</a>@endcan
        </div>
        @if($auditHistory->isEmpty())
            <div class="oversight-empty"><strong>No audited actions on this account</strong></div>
        @else
            <ul class="review-audit">
                @foreach($auditHistory as $entry)
                    <li>
                        <span class="audit-action">{{ $entry->action }}</span>
                        <span>{{ $entry->actor?->full_name ?? 'System' }}</span>
                        @foreach($entry->changes ?? [] as $field => $change)
                            <span class="oversight-meta">{{ $field }}: {{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}</span>
                        @endforeach
                        <time class="oversight-meta" datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->format('M d, Y h:i A') }}</time>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
