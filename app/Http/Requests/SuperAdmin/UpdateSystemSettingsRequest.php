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
            'systemLogoUrl' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value && !filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, 'data:image/')) {
                        $fail('The ' . $attribute . ' must be a valid URL or data URL.');
                    }
                },
                'max:10000', // Allow larger size for data URLs
            ],
            'defaultCurrency' => ['sometimes', 'string', 'size:3'],
            'defaultTimezone' => ['sometimes', 'string', 'timezone'],
        ];
    }
}
