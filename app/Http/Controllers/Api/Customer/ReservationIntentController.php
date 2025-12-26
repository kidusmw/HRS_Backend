<?php

namespace App\Http\Controllers\Api\Customer;

use App\Actions\Payments\Chapa\CreateReservationIntentAction;
use App\Actions\Payments\Chapa\InitiatePaymentForIntentAction;
use App\DTO\Payments\Chapa\CreatePaymentForIntentDto;
use App\DTO\Payments\Chapa\CreateReservationIntentDto;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservationIntentController extends Controller
{
    public function __construct(
        private CreateReservationIntentAction $createIntentAction,
        private InitiatePaymentForIntentAction $initiatePaymentAction
    ) {
    }

    /**
     * Create reservation intent and initiate payment
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_id' => 'required|integer|exists:hotels,id',
            'room_type' => 'required|string',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        $intentDto = new CreateReservationIntentDto(
            hotelId: $validated['hotel_id'],
            roomType: $validated['room_type'],
            checkIn: $validated['check_in'],
            checkOut: $validated['check_out'],
            guests: $validated['guests'],
        );

        $intent = $this->createIntentAction->execute($intentDto, $user->id);

        // Generate unique transaction reference
        $txRef = 'TXN-' . Str::random(16) . '-' . time();

        $paymentDto = new CreatePaymentForIntentDto(
            reservationIntentId: $intent->id,
            txRef: $txRef,
            amount: (float) $intent->total_amount,
            currency: $intent->currency,
            callbackUrl: route('api.webhooks.chapa'),
            returnUrl: $request->input('return_url', config('app.frontend_url') . '/reservations/payment/return'),
        );

        $chapaResponse = $this->initiatePaymentAction->execute($paymentDto);

        return response()->json([
            'message' => 'Reservation intent created and payment initiated',
            'data' => [
                'intent_id' => $intent->id,
                'checkout_url' => $chapaResponse->checkoutUrl,
                'tx_ref' => $chapaResponse->txRef,
            ],
        ], 201);
    }
}
