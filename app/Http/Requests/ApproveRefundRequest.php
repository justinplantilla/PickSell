<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApproveRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('approve', $this->route('refund'));

        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:2000']];
    }
}
