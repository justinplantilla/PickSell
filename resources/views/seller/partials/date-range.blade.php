{{-- Custom date-to-date range. Needs $action, $from, $to (Carbon), $active (bool: the custom range is in use); $keep: extra query values. --}}
<form method="GET" action="{{ $action }}" class="seller-date-range {{ $active ? 'is-active' : '' }}" aria-label="Custom date range">
    @foreach(($keep ?? []) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
    <label><span>From</span><input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" required></label>
    <label><span>To</span><input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" required></label>
    <button type="submit" class="btn btn-sm {{ $active ? 'btn-coral' : 'btn-outline' }}">Apply</button>
</form>
