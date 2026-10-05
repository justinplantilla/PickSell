<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Suspend or reinstate a seller for compliance reasons: reason required, suspension needs confirmation. */
class SuspendSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize($this->isReinstatement() ? 'reinstateSeller' : 'suspendSeller', $this->route('user'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => $this->isReinstatement() ? ['nullable'] : ['accepted'],
            'case_id' => ['nullable', 'integer', Rule::exists('compliance_cases', 'id')
                ->where('seller_id', $this->route('user')->id)
                ->whereIn('status', ['open', 'investigating'])],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required; it is kept in the seller’s compliance history.',
            'reason.min' => 'Give a clear reason (at least 10 characters).',
            'confirm.accepted' => 'Confirm that you want to suspend this seller.',
            'case_id.exists' => 'Link an open case belonging to this seller.',
        ];
    }

    public function isReinstatement(): bool
    {
        return $this->routeIs('admin.compliance.reinstate');
    }
}
