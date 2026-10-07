<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ResolveParcelExceptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['resolution' => trim((string) $this->input('resolution'))]);
    }

    public function authorize(): bool
    {
        Gate::authorize('resolveParcelException', $this->route('exception'));

        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
