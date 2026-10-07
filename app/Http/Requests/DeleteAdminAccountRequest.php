<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteAdminAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deleteAdminAccount', $this->user());
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'confirmation' => ['required', 'string', 'in:DELETE'],
        ];
    }
}
