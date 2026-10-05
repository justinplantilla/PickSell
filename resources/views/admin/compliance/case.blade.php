@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-compliance.css'])
@endsection
@section('title', "Compliance case #{$case->id}")

@php
    $canNote = auth()->user()->can('addNote', $case);
    $canResolve = auth()->user()->can('resolve', $case);
@endphp

@section('content')
<p class="compliance-back">
    <a href="{{ route('admin.compliance.seller', $case->seller) }}" class="btn btn-outline btn-sm">← {{ $case->seller->business_name ?? $case->seller->full_name }}</a>
    <a href="{{ route('admin.compliance.cases') }}" class="btn btn-outline btn-sm">All cases</a>
</p>

<div class="compliance-layout">
    <section class="card" aria-labelledby="case-heading">
        <div class="card-header">
            <span class="card-title" id="case-heading">Case #{{ $case->id }} · {{ $case->type_label }}</span>
            <span>
                <span class="severity-badge severity-{{ $case->severity }}">{{ ucfirst($case->severity) }}</span>
                <span class="badge {{ $case->isOpen() ? 'badge-pending' : 'badge-approved' }}">{{ ucfirst($case->status) }}</span>
            </span>
        </div>
        <dl class="compliance-fields">
            <div><dt>Seller</dt><dd>{{ $case->seller->business_name ?? $case->seller->full_name }} <span class="badge badge-{{ $case->seller->status }}">{{ $case->seller->status === 'approved' ? 'Active' : ucfirst($case->seller->status) }}</span></dd></div>
            <div><dt>Registered line</dt><dd>{{ $case->seller->line_of_business ?? '—' }}</dd></div>
            @if($case->product)
                <div><dt>Product</dt><dd>@can('view', $case->product)<a href="{{ route('admin.products.show', $case->product) }}">{{ $case->product->name }}</a>@else{{ $case->product->name }}@endcan · listed as {{ $case->product->category ?? '—' }}</dd></div>
            @endif
            <div><dt>Opened</dt><dd>{{ $case->created_at->format('M d, Y h:i A') }} by {{ $case->opener?->full_name ?? 'Admin' }}</dd></div>
            @if($case->resolved_at)<div><dt>Closed</dt><dd>{{ $case->resolved_at->format('M d, Y h:i A') }}</dd></div>@endif
            <div><dt>Description</dt><dd class="compliance-reason">{{ $case->description }}</dd></div>
        </dl>
    </section>

    <section class="card" aria-labelledby="timeline-heading">
        <div class="card-header"><span class="card-title" id="timeline-heading">Evidence &amp; actions</span></div>
        @include('admin.compliance._timeline', ['actions' => $case->actions])
    </section>

    @if($canNote)
        <section class="card" aria-labelledby="note-heading">
            <div class="card-header"><span class="card-title" id="note-heading">Add note or evidence</span></div>
            <form method="POST" action="{{ route('admin.compliance.cases.notes', $case) }}" enctype="multipart/form-data" class="compliance-form">
                @csrf
                <label class="form-label" for="note">Note</label>
                <textarea id="note" name="note" class="form-control" rows="3" required minlength="5" maxlength="5000">{{ old('note') }}</textarea>
                @error('note')<span class="compliance-error">{{ $message }}</span>@enderror
                <div class="compliance-grid">
                    <div>
                        <label class="form-label" for="note-severity">Change severity</label>
                        <select id="note-severity" name="severity" class="form-control">
                            <option value="">Keep {{ $case->severity }}</option>
                            @foreach(array_keys(\App\Models\ComplianceCase::SEVERITIES) as $value)
                                @continue($value === $case->severity)
                                <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($case->status === 'open')
                        <label class="compliance-confirm compliance-inline"><input type="checkbox" name="investigate" value="1"> Mark as investigating</label>
                    @endif
                </div>
                <label class="form-label" for="note-evidence">Evidence files <span class="oversight-meta">— up to 5, images or PDF</span></label>
                <input id="note-evidence" type="file" name="evidence[]" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" multiple>
                @if($errors->has('evidence.*'))<span class="compliance-error">{{ $errors->first('evidence.*') }}</span>@endif
                <button type="submit" class="btn btn-outline btn-sm">Add to case</button>
            </form>
        </section>
    @endif

    @if($canResolve)
        <section class="card" aria-labelledby="resolve-heading">
            <div class="card-header"><span class="card-title" id="resolve-heading">Close case</span></div>
            <form method="POST" action="{{ route('admin.compliance.cases.resolve', $case) }}" class="compliance-form">
                @csrf @method('PATCH')
                <fieldset class="compliance-outcome">
                    <legend class="form-label">Outcome</legend>
                    <label><input type="radio" name="outcome" value="resolved" @checked(old('outcome', 'resolved') === 'resolved')> Resolved — violation confirmed and addressed</label>
                    <label><input type="radio" name="outcome" value="dismissed" @checked(old('outcome') === 'dismissed')> Dismissed — no violation found</label>
                </fieldset>
                <label class="form-label" for="close-reason">Closing reason</label>
                <textarea id="close-reason" name="reason" class="form-control" rows="3" required minlength="10" maxlength="2000">{{ old('reason') }}</textarea>
                @error('reason')<span class="compliance-error">{{ $message }}</span>@enderror
                <p class="oversight-meta">Warn or suspend the seller from their <a href="{{ route('admin.compliance.seller', $case->seller) }}">compliance page</a> and link this case.</p>
                <button type="submit" class="btn btn-coral btn-sm">Close case</button>
            </form>
        </section>
    @elseif(! $case->isOpen())
        <p class="oversight-description">This case is {{ $case->status }}. Its record stays available here and in the seller’s compliance history.</p>
    @endif
</div>
@endsection
