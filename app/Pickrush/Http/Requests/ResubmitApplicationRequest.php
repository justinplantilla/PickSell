<?php

namespace App\Pickrush\Http\Requests;

use App\Pickrush\Support\Actor;

/**
 * A rejected company correcting its registration. The same rules as registering, except the
 * sign-in email and password stay as they are, the company may keep its own name and number,
 * and a document only needs uploading when it is being replaced.
 */
class ResubmitApplicationRequest extends RegisterCompanyRequest
{
    public function authorize(): bool
    {
        return (bool) Actor::staff()?->company?->isRejected();
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['email' => Actor::staff()?->email]);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['email'], $rules['password']);
        foreach (['valid_id', 'dti_permit'] as $document) {
            $rules[$document] = array_values(array_diff($rules[$document], ['required']));
            array_unshift($rules[$document], 'nullable');
        }

        return $rules;
    }

    protected function ownStaffId(): ?int
    {
        return Actor::staff()?->id;
    }

    protected function ownCompanyId(): ?int
    {
        return Actor::staff()?->company_id;
    }
}
