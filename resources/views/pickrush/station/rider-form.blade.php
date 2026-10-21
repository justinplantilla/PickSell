@extends('pickrush.layouts.app')
@section('title', 'Add rider')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Add rider</h1>
        <p>After the interview and orientation. The account starts as pending; another staff member verifies the documents, then you approve.</p>
    </div>
</div>
<form method="POST" action="{{ route('pickrush.station.riders.store') }}" class="pr-card">
    @csrf
    @include('pickrush.station.partials.rider-fields', ['rider' => null])
    <p class="pr-muted" style="margin-top:1rem">New riders start on probation: ₱{{ number_format(config('pickrush.policies.rider_limits.probation.cod_limit')) }} COD limit and {{ config('pickrush.policies.rider_limits.probation.max_parcels') }} parcels a day.</p>
    <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Create account</button></div>
</form>
@endsection
