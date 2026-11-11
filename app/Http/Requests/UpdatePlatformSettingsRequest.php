<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', \App\Models\PlatformSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'section' => ['nullable', 'string', 'in:general,financial,uploads,policies'],
            'platform_name' => ['sometimes', 'required', 'string', 'max:100'],
            'support_email' => ['sometimes', 'required', 'email', 'max:255'],
            'commission_rate' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'delivery_commission_rate' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'logistics_claim_cap' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:1', 'max:1000000'],
            'off_platform_keywords' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'max_file_upload_mb' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'policy' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'privacy' => ['sometimes', 'nullable', 'string', 'max:100000'],
        ];
    }
}
