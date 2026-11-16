{{-- A typed voucher code at checkout, keeping everything picked so far; code_for is whose vouchers it was typed into. --}}
<form method="GET" action="{{ route('buyer.checkout.start') }}{{ is_numeric($codeFor) ? '#store-vouchers-'.$codeFor : '#vouchers' }}" class="checkout-voucher-code">
    @foreach($query as $key => $value)
        @foreach((array) $value as $sub => $v)
            <input type="hidden" name="{{ is_array($value) ? $key.'['.$sub.']' : $key }}" value="{{ $v }}">
        @endforeach
    @endforeach
    <input type="hidden" name="code_for" value="{{ $codeFor }}">
    <label for="voucherCode-{{ $codeFor }}" class="checkout-voucher-hint">{{ $label }}</label>
    <div class="checkout-voucher-code-row">
        <input type="text" id="voucherCode-{{ $codeFor }}" name="code" class="form-control" maxlength="30" placeholder="{{ $placeholder }}" value="{{ $typed }}">
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
    </div>
    @if($error)<p class="checkout-voucher-error" role="alert">{{ $error }}</p>@endif
</form>
