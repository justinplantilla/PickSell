<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('complaint'));

        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'under_review', 'resolved', 'dismissed'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
