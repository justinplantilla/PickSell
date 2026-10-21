{{-- QR code drawn as table cells. $value: text to encode; $module: size of one square (pt). --}}
@php
    $module = $module ?? 3;
    $rows = \App\Pickrush\Support\Codes::qr($value);
@endphp
<table class="pr-qr" role="img" aria-label="QR code {{ $value }}" style="border-collapse:collapse;border-spacing:0;table-layout:fixed">
    @foreach($rows as $row)
        <tr>
            @foreach($row as $dark)<td style="width:{{ $module }}pt;height:{{ $module }}pt;padding:0;background:{{ $dark ? '#000' : '#fff' }}"></td>@endforeach
        </tr>
    @endforeach
</table>
