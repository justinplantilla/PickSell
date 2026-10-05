<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ResolveComplianceCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('resolve', $this->route('case'));

        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'in:resolved,dismissed'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Record why the case is being closed.', 'reason.min' => 'Give a clear closing reason (at least 10 characters).'];
    }
}
