<?php

namespace App\Http\Requests\Admin;

use App\Support\RegistrationChecklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Disapproval requires registrations.manage and a reason the applicant will receive. */
class DisapproveRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('disapproveRegistration', $this->route('user'));

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
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to disapprove an application.',
            'reason.min' => 'Give the applicant a clear reason (at least 10 characters).',
        ];
    }

    /** @return array<string, bool> */
    public function confirmedChecklist(): array
    {
        $known = array_keys(RegistrationChecklist::for($this->route('user')));

        return collect($this->validated('checklist') ?? [])
            ->only($known)
            ->map(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN))
            ->all();
    }
}
