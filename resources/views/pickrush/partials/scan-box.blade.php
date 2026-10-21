{{-- Scan box: handheld scanners type the tracking number and press Enter. The key makes a double submit count once. --}}
<form method="POST" action="{{ $action }}" class="pr-scan" autocomplete="off">
    @csrf
    @isset($scanKey)<input type="hidden" name="scan_key" value="{{ $scanKey }}">@endisset
    <label for="{{ $id ?? 'tracking_no' }}" class="pr-scan-label">{{ $label ?? 'Scan or type a tracking number' }}</label>
    <div class="pr-scan-row">
        <input id="{{ $id ?? 'tracking_no' }}" name="tracking_no" class="pr-input pr-scan-input" placeholder="PR…" required autofocus inputmode="text" spellcheck="false">
        <button type="submit" class="pr-btn pr-btn-primary">{{ $button ?? 'Scan' }}</button>
    </div>
</form>
