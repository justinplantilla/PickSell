{{-- How much of a voucher's usage limit is taken; nothing when it has no limit. --}}
@if($percent !== null)
<span class="voucher-usage {{ $percent >= 100 ? 'is-full' : ($percent >= 80 ? 'is-high' : '') }}">
    <span class="voucher-usage-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ $percent }}% of the uses taken"><span style="width: {{ $percent }}%"></span></span>
    <small>{{ $percent }}% used</small>
</span>
@endif
