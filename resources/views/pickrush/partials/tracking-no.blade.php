{{-- A parcel's tracking number as a link, or masked (no link) while it is on its way to this hub. Expects $parcel. --}}
@if($parcel->isHiddenFrom())
    <span class="pr-masked" title="The full number shows once the parcel is scanned in here">{{ $parcel->maskedTrackingNo() }}</span>
@else
    <a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a>
@endif
