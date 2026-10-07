<?php

namespace App\Http\Requests;

use App\Auth\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('admin.logistics.assign')) {
            Gate::authorize(Permission::LOGISTICS_ASSIGN_RIDER);
        }

        Gate::authorize('assignCourier', $this->route('order'));

        return true;
    }

    public function rules(): array
    {
        return [
            'courier_id' => [
                $this->routeIs('admin.logistics.assign') ? 'required' : 'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'courier')
                    ->where('status', 'approved')),
            ],
        ];
    }
}
