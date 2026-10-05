<?php

namespace App\Http\Requests\Admin;

use App\Support\RegistrationChecklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Approval requires registrations.manage (via RegistrationPolicy) and every checklist item confirmed. */
class ApproveRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('approveRegistration', $this->route('user'));

        return true;
    }

    public function rules(): array
    {
        $rules = [
            'checklist' => ['required', 'array'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
        foreach (RegistrationChecklist::for($this->route('user')) as $key => $item) {
            // An item whose document is missing can never be confirmed, whatever the client submits.
            $rules["checklist.{$key}"] = $item['available']
                ? ['accepted']
                : ['required', fn ($attribute, $value, $fail) => $fail($this->messages()["checklist.{$key}.accepted"])];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = ['checklist.required' => 'Complete the verification checklist before approving.'];
        foreach (RegistrationChecklist::for($this->route('user')) as $key => $item) {
            $messages["checklist.{$key}.accepted"] = $item['available']
                ? "Confirm before approving: {$item['label']}."
                : "Cannot approve: the {$item['label']} check needs a document the applicant did not upload.";
            $messages["checklist.{$key}.required"] = $messages["checklist.{$key}.accepted"];
        }

        return $messages;
    }

    /** @return array<string, bool> */
    public function confirmedChecklist(): array
    {
        return collect($this->validated('checklist'))->map(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN))->all();
    }
}
