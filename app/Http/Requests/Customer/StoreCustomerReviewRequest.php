<?php

namespace App\Http\Requests\Customer;

use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomerReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $user = $this->user();
            $hotelId = (int) $this->input('hotel_id');

            if (! $user || $hotelId <= 0) {
                return;
            }

            // Enforce one review per user per hotel
            $alreadyReviewed = Review::query()
                ->where('user_id', $user->id)
                ->where('hotel_id', $hotelId)
                ->exists();

            if ($alreadyReviewed) {
                $v->errors()->add('hotel_id', 'You have already submitted a review for this hotel.');
                return;
            }

            // Must have a checked_out reservation for this hotel
            $hasCheckedOutReservation = Reservation::query()
                ->where('user_id', $user->id)
                ->where('status', 'checked_out')
                ->whereHas('room', function ($q) use ($hotelId) {
                    $q->where('hotel_id', $hotelId);
                })
                ->exists();

            if (! $hasCheckedOutReservation) {
                $v->errors()->add('hotel_id', 'You can only review a hotel after you have checked out.');
            }
        });
    }
}


