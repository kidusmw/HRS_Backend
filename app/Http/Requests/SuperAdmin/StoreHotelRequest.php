<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{1,14}$/', 'max:20', Rule::unique('hotels', 'phone')],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'timezone' => ['nullable', 'string', 'timezone'], // Auto-determined from city/country if not provided
            'logo_path' => ['nullable', 'string', 'max:255'],
            'primary_admin_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value !== null) {
                        $user = \App\Models\User::find($value);
                        if ($user && $user->role !== \App\Enums\UserRole::ADMIN) {
                            $fail('The selected user must have the admin role.');
                        }
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone number must be in E.164 format (e.g., +251912345678)',
        ];
    }
}
