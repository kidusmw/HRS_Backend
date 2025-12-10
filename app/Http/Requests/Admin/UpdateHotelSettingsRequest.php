<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'checkInTime' => ['sometimes', 'string', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'checkOutTime' => ['sometimes', 'string', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'cancellationHours' => ['sometimes', 'numeric', 'min:0'],
            // Accept both JSON booleans and string "1"/"0"/"true"/"false"
            'allowOnlineBooking' => ['sometimes', 'boolean'],
            'requireDeposit' => ['sometimes', 'boolean'],
            'depositPercentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'emailNotifications' => ['sometimes', 'boolean'],
            'smsNotifications' => ['sometimes', 'boolean'],
        ];
    }
}


