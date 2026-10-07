<?php

namespace App\Http\Requests\Admin;

use App\Models\ComplianceCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WarnSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('warnSeller', $this->route('user'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['warning' => trim((string) $this->input('warning'))]);
    }

    public function rules(): array
    {
        $sellerId = $this->route('user')->id;

        return [
            'warning' => ['required', 'string', 'min:10', 'max:1000'],
            'case_id' => [
                'nullable',
                'integer',
                Rule::exists('compliance_cases', 'id')
                    ->where('seller_id', $sellerId)
                    ->whereIn('status', ComplianceCase::OPEN_STATUSES),
            ],
        ];
    }
}
