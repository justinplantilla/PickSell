<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** orders.override-status + a valid lifecycle transition (OrderPolicy); a reason is always required. */
class AdminOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('overrideStatus', [$this->route('order'), (string) $this->input('status')]);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Order::STATUS_LIFECYCLE)], // targets are always current statuses
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'An override needs a reason; it is shown to the buyer and seller and kept in the audit log.',
            'reason.min' => 'Give a clear reason (at least 10 characters).',
            'confirm.accepted' => 'Confirm the status override.',
        ];
    }
}
