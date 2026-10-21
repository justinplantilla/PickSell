{{-- Code 128 barcode drawn as table cells (prints from browsers and dompdf without images).
     $value: text to encode; $module: width of the thinnest bar (pt); $height: bar height (pt). --}}
@php
    $module = $module ?? 1;
    $height = $height ?? 44;
    $widths = \App\Pickrush\Support\Codes::code128($value);
@endphp
<table class="pr-code128" role="img" aria-label="Barcode {{ $value }}" style="border-collapse:collapse;border-spacing:0;table-layout:fixed;margin:0 auto">
    <tr>
        <td style="width:{{ 10 * $module }}pt;height:{{ $height }}pt;padding:0;background:#fff"></td>
        @foreach($widths as $i => $w)
            <td style="width:{{ $w * $module }}pt;height:{{ $height }}pt;padding:0;background:{{ $i % 2 === 0 ? '#000' : '#fff' }}"></td>
        @endforeach
        <td style="width:{{ 10 * $module }}pt;height:{{ $height }}pt;padding:0;background:#fff"></td>
    </tr>
</table>
