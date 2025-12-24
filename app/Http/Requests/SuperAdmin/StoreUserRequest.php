<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required_without:generatePassword',
                'nullable',
                'string',
                'min:8',
            ],
            'generatePassword' => ['sometimes', 'boolean'],
            'role' => ['required', Rule::in(['admin', 'superadmin', 'super_admin'])],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'phone_number' => ['required', 'string', 'max:20'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->sometimes('password', 'required', function ($input) {
            return !isset($input->generatePassword) || $input->generatePassword === false;
        });
    }
}
