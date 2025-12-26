<?php

namespace App\Http\Controllers\Api\Customer\Payments;

use App\Actions\Payments\Chapa\RefundChapaPaymentAction;
use App\Actions\Payments\Chapa\VerifyChapaPaymentAction;
use App\DTO\Payments\Chapa\RefundChapaPaymentRequestDto;
use App\Exceptions\Payments\PaymentNotCompletedException;
use App\Exceptions\Payments\PaymentRefundWindowExpiredException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Payment;

class ChapaPaymentController extends Controller
{
    public function __construct(
        private VerifyChapaPaymentAction $verifyAction,
        private RefundChapaPaymentAction $refundAction
    ) {
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
     * Get payment status by transaction reference (for polling)
     */
    public function status(Request $request): JsonResponse
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

        $txRef = $request->input('tx_ref');
        $user = $request->user();

        $payment = Payment::where('transaction_reference', $txRef)
            ->whereHas('reservationIntent', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with(['reservationIntent', 'reservation'])
            ->first();

        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'payment_status' => $payment->status->value,
                'intent_status' => $payment->reservationIntent?->status ?? 'unknown',
                'reservation_id' => $payment->reservation_id,
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

        $this->refundAction->execute($dto);

        return response()->json([
            'message' => 'Refund request processed successfully',
        ]);
    }
}

