<?php

namespace App\Http\Requests\Admin;

use App\Models\ComplianceCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AddComplianceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('addNote', $this->route('case'));

        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:5', 'max:5000'],
            'severity' => ['nullable', Rule::in(array_keys(ComplianceCase::SEVERITIES))],
            'investigate' => ['nullable', 'boolean'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => CreateComplianceCaseRequest::EVIDENCE_RULES,
        ];
    }
}
