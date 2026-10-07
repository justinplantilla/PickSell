<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAdminAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateAdminAccount', $this->user());
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'contact_no' => ['required', 'string', 'max:20'],
            'current_password' => ['required', 'current_password'],
        ];
    }
}
