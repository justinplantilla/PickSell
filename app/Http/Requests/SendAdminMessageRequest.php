<?php

namespace App\Http\Requests;

use App\Auth\Permission;
use Illuminate\Foundation\Http\FormRequest;

class SendAdminMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::MESSAGING_MANAGE) ?? false;
    }

    public function rules(): array
    {
        return [
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
