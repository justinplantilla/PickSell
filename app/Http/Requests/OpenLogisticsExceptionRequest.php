<?php

namespace App\Http\Requests;

use App\Models\LogisticsException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OpenLogisticsExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('openException', $this->route('order'));

        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(LogisticsException::TYPES)],
            'description' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
