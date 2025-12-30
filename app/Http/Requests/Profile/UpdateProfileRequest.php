<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id ?? null;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $userId,
            'phoneNumber' => 'nullable|string|regex:/^\+[1-9]\d{1,14}$/|unique:users,phone_number,' . $userId,
            'avatar' => 'nullable|image|max:5120', // 5MB
            'removeAvatar' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'phoneNumber.regex' => 'Phone number must be in E.164 format (e.g., +251912345678)',
        ];
    }
}

