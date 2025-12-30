<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by route middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id');
        
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => [
                'sometimes',
                Rule::in(['client', 'admin', 'superadmin', 'super_admin']),
                function ($attribute, $value, $fail) {
                    // Prevent changing a user's role TO client (clients must self-register)
                    // But allow keeping existing client role if user is already a client
                    $user = \App\Models\User::find($this->route('id'));
                    if ($user && $user->role->value !== 'client' && $value === 'client') {
                        $fail('Clients must self-register. You cannot assign the client role to existing users.');
                    }
                },
            ],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'phone_number' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone_number')->ignore($userId)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
