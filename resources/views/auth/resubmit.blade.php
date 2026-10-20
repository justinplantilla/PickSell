@extends('layouts.app')
@section('title', 'Resubmit Application')

@section('styles')
@vite('resources/css/views/auth-register.css')
@endsection

@section('content')
<div class="auth-wrapper">
    <div class="auth-shell">
        <div class="auth-card">
        <div class="auth-logo"><a href="/"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a></div>
        <h1 class="auth-title">Resubmit your {{ $applicant->role === 'logistics' ? 'logistics provider' : 'seller' }} application</h1>
        <p class="auth-sub">{{ $applicant->business_name }} · {{ $applicant->email }}</p>

        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <div class="notice-box">
            <strong>Why it was not approved:</strong>
            <span>{{ $reason ?: 'No reason was given. Check your documents and upload clearer copies.' }}</span>
        </div>

        <form method="POST" action="{{ route('registration.resubmit.store') }}" enctype="multipart/form-data" id="resubmitForm">
            @csrf
            <div class="form-section">
                <div class="form-section-title">Replace documents</div>
                <p class="field-hint">Upload a corrected copy of each document that needs fixing. Documents you leave empty stay as they are.</p>
                @foreach($documents as $field => $label)
                    <div class="form-group">
                        <label>{{ $label }}</label>
                        <label class="file-upload-label" for="{{ $field }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 6v11.5a4 4 0 0 1-8 0V5a2.5 2.5 0 0 1 5 0v10.5a1 1 0 0 1-2 0V6H10v9.5a2.5 2.5 0 0 0 5 0V5a4 4 0 0 0-8 0v12.5a5.5 5.5 0 0 0 11 0V6h-1.5z"/></svg>
                            <span id="{{ $field }}_name">Click to upload (JPG, PNG, PDF — max 5MB)</span>
                            <input type="file" id="{{ $field }}" name="{{ $field }}" accept=".jpg,.jpeg,.png,.pdf" data-file-name-target="{{ $field }}_name">
                        </label>
                        @error($field)<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>

            <button type="submit" class="btn-submit">Resubmit Application</button>
        </form>

        <div class="auth-footer"><a href="/login">Back to log in</a></div>
        </div>
    </div>
</div>

@section('scripts')
<script>
    document.querySelectorAll('[data-file-name-target]').forEach(input => input.addEventListener('change', () => {
        document.getElementById(input.dataset.fileNameTarget).textContent = input.files[0]?.name || 'Click to upload (JPG, PNG, PDF — max 5MB)';
    }));
    document.getElementById('resubmitForm').addEventListener('submit', event => {
        const button = event.target.querySelector('.btn-submit');
        if (button.disabled) { event.preventDefault(); return; }
        setTimeout(() => { button.disabled = true; button.textContent = 'Resubmitting...'; }, 0);
    });
</script>
@endsection
@endsection
