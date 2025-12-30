<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreReservationIntentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hotel_id' => 'required|integer|exists:hotels,id',
            'room_type' => 'required|string',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'required|integer|min:1',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            
            if (!$user) {
                $validator->errors()->add('user', 'User not authenticated');
                return;
            }

            // Check if user has a valid E.164 phone number
            if (empty($user->phone_number)) {
                $validator->errors()->add('phoneNumber', 'Please complete your profile by adding a phone number before making a reservation.');
                return;
            }

            // Validate phone number format (E.164)
            if (!preg_match('/^\+[1-9]\d{1,14}$/', $user->phone_number)) {
                $validator->errors()->add('phoneNumber', 'Your phone number is not in a valid format. Please update it in your profile (E.164 format, e.g., +251912345678).');
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
