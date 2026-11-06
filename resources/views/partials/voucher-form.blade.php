{{-- Voucher form for store vouchers (seller) and PickSell vouchers (admin). $voucher is null to create; $types lists the allowed kinds. --}}
@php $key = $voucher?->id ?? 'new'; @endphp
<form method="POST" action="{{ $action }}" class="voucher-form">
    @csrf
    @if($voucher) @method('PUT') @endif
    <div class="voucher-form-grid">
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-code">Code *</label>
            <input type="text" id="voucher-{{ $key }}-code" name="code" class="form-control voucher-code-input" maxlength="30" required placeholder="SAVE50" value="{{ old('code', $voucher?->code) }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-title">Title *</label>
            <input type="text" id="voucher-{{ $key }}-title" name="title" class="form-control" maxlength="80" required placeholder="₱50 off orders over ₱500" value="{{ old('title', $voucher?->title) }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-type">Kind *</label>
            <select id="voucher-{{ $key }}-type" name="type" class="form-control" required>
                @foreach($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $voucher?->type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-amount">Amount (₱ or %)</label>
            <input type="number" id="voucher-{{ $key }}-amount" name="amount" class="form-control" min="0" step="0.01" placeholder="50" value="{{ old('amount', $voucher && $voucher->type !== \App\Models\Voucher::FREE_SHIPPING ? $voucher->amount : null) }}">
            <small class="form-hint">Leave empty for free shipping.</small>
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-min">Minimum spend (₱)</label>
            <input type="number" id="voucher-{{ $key }}-min" name="min_spend" class="form-control" min="0" step="0.01" placeholder="0" value="{{ old('min_spend', $voucher?->min_spend) }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-cap">Maximum discount (₱)</label>
            <input type="number" id="voucher-{{ $key }}-cap" name="max_discount" class="form-control" min="0" step="0.01" placeholder="No cap" value="{{ old('max_discount', $voucher?->max_discount) }}">
            <small class="form-hint">For % off and free shipping.</small>
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-limit">Total uses</label>
            <input type="number" id="voucher-{{ $key }}-limit" name="usage_limit" class="form-control" min="1" step="1" placeholder="Unlimited" value="{{ old('usage_limit', $voucher?->usage_limit) }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-per">Uses per buyer *</label>
            <input type="number" id="voucher-{{ $key }}-per" name="per_buyer_limit" class="form-control" min="1" max="100" step="1" required value="{{ old('per_buyer_limit', $voucher?->per_buyer_limit ?? 1) }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-start">Starts</label>
            <input type="datetime-local" id="voucher-{{ $key }}-start" name="starts_at" class="form-control" value="{{ old('starts_at', $voucher?->starts_at?->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="voucher-{{ $key }}-end">Ends</label>
            <input type="datetime-local" id="voucher-{{ $key }}-end" name="ends_at" class="form-control" value="{{ old('ends_at', $voucher?->ends_at?->format('Y-m-d\TH:i')) }}">
        </div>
    </div>
    <label class="voucher-active"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $voucher?->is_active ?? true))> Active</label>
    <button type="submit" class="btn btn-coral btn-sm">{{ $voucher ? 'Save voucher' : 'Create voucher' }}</button>
</form>
