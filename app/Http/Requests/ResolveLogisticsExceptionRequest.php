<?php

namespace App\Http\Requests;

use App\Services\Orders\OrderAdministrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ResolveLogisticsExceptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    public function authorize(): bool
    {
        Gate::authorize('resolveLogisticsException', [
            $this->route('order'),
            (string) $this->input('action'),
        ]);

        return true;
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
