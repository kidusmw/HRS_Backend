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
            // NOTE: SVG is not considered an "image" by Laravel's `image` rule, so we validate via mimes/mimetypes.
            'logo' => [
                'sometimes',
                'file',
                'max:5120', // 5MB max
                'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml',
                'mimes:jpg,jpeg,png,webp,svg',
            ],
            // Removed systemLogoUrl - only file uploads allowed
            // Currency and timezone are fixed to USD/UTC, no longer editable
            // Payment options
            'chapaEnabled' => ['sometimes', 'boolean'],
            'stripeEnabled' => ['sometimes', 'boolean'],
            'telebirrEnabled' => ['sometimes', 'boolean'],
        ];
    }
}
