<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('assignCourier', $this->route('order'));

        return true;
    }

    public function rules(): array
    {
        return [
            'courier_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'courier')
                    ->where('status', 'approved')),
            ],
        ];
    }
}
