<?php

namespace App\Http\Controllers\Api\Customer\Payments;

use App\Actions\Payments\Chapa\InitiateChapaPaymentAction;
use App\Actions\Payments\Chapa\RefundChapaPaymentAction;
use App\Actions\Payments\Chapa\VerifyChapaPaymentAction;
use App\DTO\Payments\Chapa\InitiateChapaPaymentRequestDto;
use App\DTO\Payments\Chapa\RefundChapaPaymentRequestDto;
use App\Exceptions\Payments\PaymentNotCompletedException;
use App\Exceptions\Payments\PaymentRefundWindowExpiredException;
use App\Exceptions\Payments\ReservationAlreadyPaidException;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChapaPaymentController extends Controller
{
    public function __construct(
        private InitiateChapaPaymentAction $initiateAction,
        private VerifyChapaPaymentAction $verifyAction,
        private RefundChapaPaymentAction $refundAction
    ) {
    }

    /**
     * Initiate Chapa payment for a reservation
     */
    public function initiate(Request $request, int $reservationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|integer|exists:reservations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $reservation = Reservation::with('room')->findOrFail($reservationId);

        $dto = new InitiateChapaPaymentRequestDto(
            reservationId: $reservation->id,
            amount: (float) $reservation->total_amount,
            currency: 'ETB',
            customerName: $reservation->guest_name ?? $reservation->user?->name ?? 'Guest',
            customerEmail: $reservation->guest_email ?? $reservation->user?->email ?? '',
            customerPhone: $reservation->guest_phone ?? $reservation->user?->phone_number,
            callbackUrl: route('api.payments.chapa.callback'),
            returnUrl: $request->input('return_url', config('app.frontend_url') . '/reservations/' . $reservationId . '/payment/return'),
        );

        try {
            $response = $this->initiateAction->execute($dto);

            return response()->json([
                'message' => 'Payment initiated successfully',
                'data' => [
                    'checkout_url' => $response->checkoutUrl,
                    'tx_ref' => $response->txRef,
                ],
            ], 201);
        } catch (ReservationAlreadyPaidException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify Chapa payment
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tx_ref' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $response = $this->verifyAction->execute($request->input('tx_ref'));

        return response()->json([
            'message' => 'Payment verified',
            'data' => [
                'tx_ref' => $response->txRef,
                'status' => $response->chapaStatus,
            ],
        ]);
    }

    /**
     * Request refund for a payment
     */
    public function refund(Request $request, int $paymentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $dto = new RefundChapaPaymentRequestDto(
            paymentId: $paymentId,
            reason: $request->input('reason'),
        );

        try {
            $this->refundAction->execute($dto);

            return response()->json([
                'message' => 'Refund request processed successfully',
            ]);
        } catch (PaymentNotCompletedException | PaymentRefundWindowExpiredException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

