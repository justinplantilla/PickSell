@extends('logistics.layout')
@section('title', 'Rider Applications')
@section('no_intro', true)
@section('content')
<div class="page-heading">
    <div>
        <h1>Rider Applications</h1>
        <p>Couriers who registered from {{ $station->municipality?->name }}, {{ $station->municipality?->province }}. Check their documents, then approve them to make them riders at this station, or disapprove with a reason they will receive by email.</p>
    </div>
    <div class="page-heading-actions"><a class="btn btn-outline" href="{{ route('station-hub.riders') }}">Riders</a></div>
</div>

<div class="callout callout--info">
    <div>Applicants sign up at <span class="portal-link">https://{{ \App\Support\LogisticsDomains::superadminHost() }}/register</span> as a <strong>Courier</strong> and enter {{ $station->municipality?->name }} as their municipality.</div>
</div>

@forelse($applications as $applicant)
    @php
        $checklist = \App\Support\RegistrationChecklist::for($applicant);
        $documents = \App\Support\RegistrationChecklist::documents($applicant);
        $missingDocument = collect($checklist)->contains('available', false);
    @endphp
    <section class="card" aria-labelledby="applicant-{{ $applicant->id }}">
        <div class="card-header">
            <span class="card-title" id="applicant-{{ $applicant->id }}">{{ $applicant->full_name }}</span>
            <span class="cell-meta">Applied {{ $applicant->created_at?->diffForHumans() }}</span>
        </div>
        <div class="application-grid">
            <div>
                <dl class="application-facts">
                    <div><dt>Email</dt><dd>{{ $applicant->email }}</dd></div>
                    <div><dt>Mobile</dt><dd>{{ $applicant->contact_no ?? '—' }}</dd></div>
                    <div><dt>Address</dt><dd>{{ collect([$applicant->house_no, $applicant->street, $applicant->barangay, $applicant->municipality])->filter()->join(', ') ?: '—' }}</dd></div>
                    <div><dt>Age</dt><dd>{{ $applicant->age ?? '—' }}</dd></div>
                    <div><dt>Vehicle</dt><dd>{{ $applicant->vehicle_type ? ucfirst($applicant->vehicle_type) : '—' }}</dd></div>
                    <div><dt>Plate number</dt><dd>{{ $applicant->plate_number ?? '—' }}</dd></div>
                    <div><dt>Preferred delivery area</dt><dd>{{ $applicant->delivery_area ?? '—' }}</dd></div>
                </dl>
                <div class="application-docs">
                    @forelse($documents as $document)
                        <a class="btn btn-outline btn-sm" href="{{ asset('storage/'.$document['path']) }}" target="_blank" rel="noopener">View {{ $document['label'] }} ↗</a>
                    @empty
                        <span class="status-pill status-pill--warning">No documents uploaded</span>
                    @endforelse
                </div>
            </div>
            <div class="application-decide">
                @if($missingDocument)
                    <div class="callout"><div>A required document is missing, so this application can only be disapproved.</div></div>
                @else
                    <form method="POST" action="{{ route('station-hub.applications.approve', $applicant) }}" data-confirm-message="Approve {{ $applicant->full_name }} as a rider at {{ $station->name }}?">
                        @csrf @method('PATCH')
                        <h3>Verify before approving</h3>
                        <ul class="application-checklist">
                            @foreach($checklist as $key => $item)
                                <li><label><input type="checkbox" name="checklist[{{ $key }}]" value="1" required> {{ $item['label'] }}</label></li>
                            @endforeach
                        </ul>
                        <label class="cell-meta" for="barangay-{{ $applicant->id }}">First barangay to cover (optional)</label>
                        <select class="form-control" id="barangay-{{ $applicant->id }}" name="barangay_id">
                            <option value="">Assign later on the Riders page</option>
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay->id }}" @selected(\App\Support\PlaceName::same($barangay->name, $applicant->delivery_area))>{{ $barangay->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-success" type="submit">Approve as Rider</button>
                    </form>
                @endif
                <details class="application-disapprove" @if($missingDocument) open @endif>
                    <summary>Disapprove application</summary>
                    <form method="POST" action="{{ route('station-hub.applications.disapprove', $applicant) }}" data-confirm-message="Disapprove {{ $applicant->full_name }}? They will be emailed your reason.">
                        @csrf @method('PATCH')
                        <label class="cell-meta" for="reason-{{ $applicant->id }}">Reason (sent to the applicant)</label>
                        <textarea class="form-control" id="reason-{{ $applicant->id }}" name="reason" rows="3" minlength="5" maxlength="1000" required placeholder="e.g. The OR/CR photo is unreadable. Please register again with a clear copy."></textarea>
                        <button class="btn btn-outline" type="submit">Disapprove</button>
                    </form>
                </details>
            </div>
        </div>
    </section>
@empty
    <div class="card"><div class="empty-state"><strong>No pending applications</strong><p>New courier applications from {{ $station->municipality?->name }} will appear here.</p></div></div>
@endforelse
@endsection
