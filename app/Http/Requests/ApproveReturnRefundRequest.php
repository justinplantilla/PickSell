<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApproveReturnRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('approveRefund', $this->route('returnRequest'));

        return true;
    }

    public function rules(): array
    {
        $returnRequest = $this->route('returnRequest');

        return [
            'admin_notes' => ['required', 'string', 'min:3', 'max:2000'],
            'refund_amount' => [
                'required',
                'numeric',
                'gt:0',
                'lte:'.(float) $returnRequest->order()->value('amount'),
            ],
        ];
    }
}
