<?php

namespace App\Http\Requests;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ResolveComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('complaint'));

        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['resolved', 'dismissed'])],
            'resolution_type' => ['required', Rule::in(Complaint::RESOLUTION_TYPES)],
            'resolution_notes' => ['required', 'string', 'max:2000'],
            'refund_amount' => [
                Rule::requiredIf($this->input('resolution_type') === 'refund'),
                'nullable',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
