<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'systemName' => ['sometimes', 'string', 'max:255'],
            'systemLogoUrl' => ['nullable', 'string', 'url', 'max:500'],
            'defaultCurrency' => ['sometimes', 'string', 'size:3'],
            'defaultTimezone' => ['sometimes', 'string', 'timezone'],
        ];
    }
}
