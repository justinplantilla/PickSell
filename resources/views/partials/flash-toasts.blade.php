{{--
    This request's messages for notify.js to show as toasts: session success/info/warning/error and
    the first validation error. A page may pass $errorMessage to say something better than that
    first error. Without JavaScript the hidden list shows as plain notes instead.
--}}
@php
    $flashToasts = collect(['success', 'info', 'warning', 'error'])
        ->map(fn (string $type) => [$type, session($type)])
        ->filter(fn (array $toast) => is_string($toast[1]) && $toast[1] !== '');
    $validationMessage = $errorMessage ?? (isset($errors) ? $errors->first() : null);
    if ($validationMessage && ! $flashToasts->contains(fn (array $toast) => $toast[1] === $validationMessage)) {
        $flashToasts->push(['error', $validationMessage]);
    }
@endphp
@if($flashToasts->isNotEmpty())
<div class="flash-toasts" hidden data-flash-toasts>
    @foreach($flashToasts as [$type, $message])
        <p data-flash="{{ $type }}">{{ $message }}</p>
    @endforeach
</div>
<noscript><style>.flash-toasts[hidden] { display: block !important; }</style></noscript>
@endif
