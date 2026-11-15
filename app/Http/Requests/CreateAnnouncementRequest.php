<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAnnouncementRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        return route('admin.settings.index', ['tab' => 'announcements']);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('update', \App\Models\PlatformSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'audience' => ['required', 'string', 'in:all,buyer,seller'],
        ];
    }
}
