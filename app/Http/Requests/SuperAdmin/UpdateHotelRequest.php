<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'country' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone'], // Auto-determined from city/country if not provided
            'logo_path' => ['nullable', 'string', 'max:255'],
            'primary_admin_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '') {
                        $user = \App\Models\User::find($value);
                        if ($user && $user->role !== \App\Enums\UserRole::ADMIN) {
                            $fail('The selected user must have the admin role.');
                        }
                    }
                },
            ],
        ];
    }
}
