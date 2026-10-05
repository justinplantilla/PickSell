<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** products.moderate via ProductPolicy; archiving needs a reason the seller will see. */
class ModerateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('moderate', [$this->route('product'), (string) $this->input('status')]);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,archived'],
            'reason' => $this->input('status') === 'archived'
                ? ['required', 'string', 'min:10', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to archive a product; the seller will see it.',
            'reason.min' => 'Give the seller a clear reason (at least 10 characters).',
        ];
    }
}
