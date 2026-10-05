<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** products.moderate via ProductPolicy (only active products can be featured); reason optional. */
class ToggleFeaturedRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('toggleFeatured', $this->route('product'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason')) ?: null]);
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }
}
