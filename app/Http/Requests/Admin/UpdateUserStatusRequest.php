<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\UserModerationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** users.manage + a valid transition (UserPolicy); suspension/deactivation need a reason and confirmation. */
class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('updateStatus', [$this->route('user'), (string) $this->input('status')]);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason')) ?: null]);
    }

    public function rules(): array
    {
        $blocking = $this->blocksSignIn();

        return [
            'status' => ['required', 'in:' . implode(',', array_keys(UserModerationService::ACTION_LABELS))],
            'reason' => $blocking ? ['required', 'string', 'min:10', 'max:1000'] : ['nullable', 'string', 'max:1000'],
            'confirm' => $blocking ? ['accepted'] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        $action = strtolower(UserModerationService::ACTION_LABELS[$this->input('status')] ?? 'change');

        return [
            'reason.required' => "A reason is required to {$action} an account.",
            'reason.min' => 'Give a clear reason (at least 10 characters); it is kept in the account history.',
            'confirm.accepted' => "Confirm that you want to {$action} this account.",
        ];
    }

    private function blocksSignIn(): bool
    {
        return in_array($this->input('status'), UserModerationService::REQUIRES_CONFIRMATION, true);
    }
}
