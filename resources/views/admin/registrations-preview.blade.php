{{-- Applicant summary, documents and a quick disapproval, loaded into the side panel on the registration queue. --}}
@php
    $review = $reviews->first();
    $isImage = fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    $address = collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->join(', ');
@endphp
<div class="list-preview registration-preview">
    <p class="list-preview-kicker">{{ ucfirst($user->role) }} application</p>
    <h2 id="list-drawer-title">{{ $user->full_name }}</h2>
    <div class="list-preview-status">
        <span class="badge badge-{{ $user->status }}">{{ ucfirst($user->status) }}</span>
        <span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
        <span class="oversight-meta">Submitted {{ $user->created_at->format('M d, Y') }} · {{ $user->created_at->diffForHumans() }}</span>
    </div>

    @if($sameName->isNotEmpty())
        <div class="registration-preview-warning">
            <strong>Same name as {{ $sameName->count() }} other {{ $sameName->count() === 1 ? 'account' : 'accounts' }}</strong>
            <ul>
                @foreach($sameName as $other)
                    <li>{{ $other->email }} · {{ ucfirst($other->role) }} · {{ ucfirst($other->status) }} · {{ $other->created_at->format('M d, Y') }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <dl class="registration-fields">
        <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
        <div><dt>Mobile</dt><dd>{{ $user->contact_no ?: '—' }}</dd></div>
        <div><dt>Birthday</dt><dd>{{ $user->birthday ? \Illuminate\Support\Carbon::parse($user->birthday)->format('M d, Y') : '—' }}</dd></div>
        <div><dt>Address</dt><dd>{{ $address ?: '—' }}</dd></div>
        @if($user->role === 'seller')
            <div><dt>Seller type</dt><dd>{{ \App\Http\Controllers\AdminRegistrationController::sellerTypeLabel($user) }}</dd></div>
            <div><dt>Shop name</dt><dd>{{ $user->business_name ?: '—' }}</dd></div>
            <div><dt>Line of business</dt><dd>{{ $user->line_of_business ?: '—' }}</dd></div>
            <div><dt>TIN</dt><dd>{{ $user->tin ?: '—' }}</dd></div>
        @endif
    </dl>

    <h3 class="list-preview-heading">Documents · {{ $documentStatus['uploaded'] }} of {{ $documentStatus['required'] }}</h3>
    @if(! $documentStatus['complete'])
        <p class="oversight-flag">Missing: {{ implode(', ', $documentStatus['missing']) }}. This application can only be disapproved until it is complete.</p>
    @endif
    @if($documents)
        <ul class="registration-preview-docs">
            @foreach($documents as $document)
                <li>
                    <a href="{{ asset('storage/'.$document['path']) }}" target="_blank" rel="noopener">
                        @if($isImage($document['path']))
                            <img src="{{ asset('storage/'.$document['path']) }}" alt="{{ $document['label'] }}" loading="lazy">
                        @else
                            <span class="registration-preview-file" aria-hidden="true">PDF</span>
                        @endif
                        <span>{{ $document['label'] }}<span class="sr-only"> (opens in new tab)</span></span>
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <p class="oversight-meta">No documents uploaded.</p>
    @endif

    @if($review)
        <h3 class="list-preview-heading">{{ $user->status === 'pending' ? 'Previous decision' : 'Decision' }}</h3>
        <p class="registration-preview-decision">
            <strong>{{ ucfirst($review->decision) }}</strong> by {{ $review->reviewer?->full_name ?? 'Admin' }} · {{ $review->reviewed_at->format('M d, Y') }}
            @if($review->reason)<span class="registration-preview-reason">{{ $review->reason }}</span>@endif
        </p>
    @endif

    <a href="{{ route('admin.registrations.show', $user) }}" class="btn btn-coral list-preview-open">{{ $user->status === 'pending' ? 'Review & decide' : 'Open full review' }}</a>

    @if($user->status === 'pending')
        @can('disapproveRegistration', $user)
            <details class="registration-quick-disapprove">
                <summary>Disapprove without the full review</summary>
                <form method="POST" action="{{ route('admin.registrations.disapprove', $user) }}" class="registration-action-form"
                      data-confirm="Disapprove this application and email the reason to the applicant?">
                    @csrf
                    @method('PATCH')
                    <label class="form-label" for="preview-disapprove-reason">Reason sent to the applicant</label>
                    <textarea id="preview-disapprove-reason" name="reason" class="form-control" rows="3" minlength="10" maxlength="1000" required></textarea>
                    <button type="submit" class="btn btn-outline btn-sm registration-disapprove-button">Disapprove</button>
                </form>
            </details>
        @endcan
    @endif
</div>
