@extends('pickrush.layouts.app')
@section('title', 'Exceptions')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Exceptions</h1>
        <p>Missing, damaged, mis-sorted, unlabeled and no-route parcels. A parcel with an open problem stays on hold so it cannot leave by accident.</p>
    </div>
</div>

<nav class="pr-tabs" aria-label="Status">
    <a href="{{ route('pickrush.exceptions.index') }}" @class(['is-active' => $status === 'open'])>Open</a>
    <a href="{{ route('pickrush.exceptions.index', ['status' => 'resolved']) }}" @class(['is-active' => $status === 'resolved'])>Resolved</a>
</nav>

@if($status === 'open')
    <form method="POST" action="{{ route('pickrush.exceptions.store') }}" enctype="multipart/form-data" class="pr-card pr-section">
        @csrf
        <h2>Report a problem</h2>
        <div class="pr-form-grid">
            <div class="pr-field"><label for="type">Type</label>
                <select id="type" name="type" class="pr-select">@foreach($reportable as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="pr-field"><label for="ex_tracking">Tracking number</label><input id="ex_tracking" name="tracking_no" class="pr-input" value="{{ old('tracking_no') }}" placeholder="Leave empty if unlabeled"></div>
            <div class="pr-field"><label for="ex_note">What is wrong</label><input id="ex_note" name="note" class="pr-input" value="{{ old('note') }}" required></div>
            <div class="pr-field"><label for="photo">Photo</label><input id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png"></div>
        </div>
        <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Report</button></div>
    </form>
@endif

<section class="pr-section">
    <h2>Found at this hub</h2>
    @include('pickrush.exceptions.table', ['rows' => $found, 'resolvable' => $status === 'open', 'showAgainst' => true])
</section>

@if($against->isNotEmpty())
    <section class="pr-section">
        <h2>Reported against this hub</h2>
        <p class="pr-muted">Another hub could not account for these. The hub that cannot produce a scan owns the loss.</p>
        @include('pickrush.exceptions.table', ['rows' => $against, 'resolvable' => false, 'showAgainst' => false])
    </section>
@endif
@endsection
