<?php

namespace App\Support;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The voucher form both sellers (store vouchers) and admins (PickSell vouchers) fill in. */
final class VoucherForm
{
    /**
     * @param  array<string, string>  $types  allowed types (Voucher::STORE_TYPES or PLATFORM_TYPES)
     * @return array<string, mixed>  attributes ready to save
     */
    public static function validated(Request $request, ?int $sellerId, ?Voucher $existing, array $types): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash:ascii',
                Rule::unique('vouchers', 'code')->where(fn ($q) => $sellerId ? $q->where('seller_id', $sellerId) : $q->whereNull('seller_id'))->ignore($existing?->id)],
            'title' => 'required|string|max:80',
            'type' => ['required', Rule::in(array_keys($types))],
            'amount' => ['nullable', 'required_unless:type,'.Voucher::FREE_SHIPPING, 'numeric', 'min:0.01', Rule::when($request->input('type') === Voucher::PERCENT, 'max:100')],
            'min_spend' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0.01',
            'usage_limit' => 'nullable|integer|min:1',
            'per_buyer_limit' => 'required|integer|min:1|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ], [
            'code.alpha_dash' => 'Use letters, numbers, dashes or underscores only, e.g. SAVE50.',
            'code.unique' => 'You already have a voucher with this code.',
            'amount.required_unless' => 'Enter how much the voucher takes off.',
            'amount.max' => 'A percent voucher can take off at most 100%.',
            'ends_at.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        return [
            'seller_id' => $sellerId,
            'amount' => $data['type'] === Voucher::FREE_SHIPPING ? 0 : $data['amount'],
            'min_spend' => $data['min_spend'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ] + collect($data)->except(['amount', 'min_spend'])->all();
    }
}
