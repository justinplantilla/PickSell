<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ChangeAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('changeAdminPassword', $this->user());
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ];
    }
}
