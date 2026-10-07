<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ScanParcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('scanParcel', $this->route('order'));

        return true;
    }

    public function rules(): array
    {
        return [
            'scan_type' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
