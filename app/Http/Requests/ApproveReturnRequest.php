<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApproveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('approve', $this->route('returnRequest'));

        return true;
    }

    public function rules(): array
    {
        return ['admin_notes' => ['required', 'string', 'min:3', 'max:2000']];
    }
}
