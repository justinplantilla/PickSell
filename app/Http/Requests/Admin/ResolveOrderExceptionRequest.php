<?php

namespace App\Http\Requests\Admin;

use App\Services\Orders\OrderAdministrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** orders.manage + a remedy that applies to the order's current state (OrderPolicy); reason required. */
class ResolveOrderExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('resolveException', [$this->route('order'), (string) $this->input('action')]);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(array_keys(OrderAdministrationService::EXCEPTION_ACTIONS))],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Record why this exception is being resolved this way.',
            'reason.min' => 'Give a clear reason (at least 10 characters).',
        ];
    }
}
