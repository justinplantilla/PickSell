<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RejectRefundRequest extends FormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:2000']];
    }

    public function authorize(): bool
    {
        Gate::authorize('reject', $this->route('refund'));

        return true;
    }
}
