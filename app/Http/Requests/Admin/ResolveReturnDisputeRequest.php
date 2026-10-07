<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ResolveReturnDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('resolveDispute', $this->route('returnRequest'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['admin_notes' => trim((string) $this->input('admin_notes'))]);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve_return,uphold_rejection'],
            'admin_notes' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
