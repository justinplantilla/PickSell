<?php

namespace App\Http\Requests\Admin;

use App\Models\ComplianceCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateComplianceCaseRequest extends FormRequest
{
    public const EVIDENCE_RULES = ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    public function authorize(): bool
    {
        Gate::authorize('openComplianceCase', $this->route('user'));

        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(ComplianceCase::TYPES))],
            'severity' => ['required', Rule::in(array_keys(ComplianceCase::SEVERITIES))],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            // A product, when given, must belong to this seller.
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('seller_id', $this->route('user')->id)],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => self::EVIDENCE_RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'description.min' => 'Describe the violation and its evidence (at least 20 characters).',
            'product_id.exists' => 'That product does not belong to this seller.',
            'evidence.max' => 'Attach at most 5 evidence files.',
            'evidence.*.mimes' => 'Evidence must be an image (JPG, PNG, WEBP) or a PDF.',
            'evidence.*.max' => 'Each evidence file must be 5MB or smaller.',
        ];
    }
}
