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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
            'phoneNumber' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|max:5120', // 5MB
            'removeAvatar' => 'sometimes|boolean',
        ];
    }
}

