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
            'logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB max
            // Removed systemLogoUrl - only file uploads allowed
            // Currency and timezone are fixed to USD/UTC, no longer editable
            // Payment options
            'chapaEnabled' => ['sometimes', 'boolean'],
            'stripeEnabled' => ['sometimes', 'boolean'],
            'telebirrEnabled' => ['sometimes', 'boolean'],
        ];
    }
}
