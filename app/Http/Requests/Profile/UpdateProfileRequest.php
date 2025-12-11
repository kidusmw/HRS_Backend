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
            'phoneNumber' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|max:5120', // 5MB
            'removeAvatar' => 'sometimes|boolean',
        ];
    }
}

