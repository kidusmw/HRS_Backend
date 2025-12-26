<?php

namespace App\Actions\Payments\Chapa;

use App\DTO\Payments\Chapa\CreatePaymentForIntentDto;
use App\DTO\Payments\Chapa\InitiateChapaPaymentRequestDto;
use App\DTO\Payments\Chapa\InitiateChapaPaymentResponseDto;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Models\Payment;
use App\Models\ReservationIntent;
use App\Services\Chapa\ChapaClient;
use Illuminate\Support\Facades\DB;

class InitiatePaymentForIntentAction
{
    public function __construct(
        private ChapaClient $chapaClient
    ) {
    }

    /**
     * Create payment record FIRST, then call Chapa
     */
    public function execute(CreatePaymentForIntentDto $dto): InitiateChapaPaymentResponseDto
    {
        $intent = ReservationIntent::with('user', 'hotel')->findOrFail($dto->reservationIntentId);

        if ($intent->status !== 'pending') {
            throw new \Exception('Reservation intent is not pending');
        }

        return DB::transaction(function () use ($dto, $intent) {
            // Create payment record BEFORE calling gateway
            $payment = Payment::create([
                'reservation_intent_id' => $dto->reservationIntentId,
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'method' => PaymentMethod::CHAPA,
                'status' => PaymentTransactionStatus::INITIATED,
                'transaction_reference' => $dto->txRef,
                'meta' => [
                    'created_at' => now()->toIso8601String(),
                ],
            ]);

            // Now call Chapa
            $chapaDto = new InitiateChapaPaymentRequestDto(
                txRef: $dto->txRef,
                amount: $dto->amount,
                currency: $dto->currency,
                customerName: $intent->user->name,
                customerEmail: $intent->user->email,
                customerPhone: $intent->user->phone_number,
                callbackUrl: $dto->callbackUrl,
                returnUrl: $dto->returnUrl,
            );

            $chapaResponse = $this->chapaClient->initiatePayment($chapaDto);

            // Update payment with Chapa response
            $payment->meta = array_merge($payment->meta ?? [], [
                'chapa_init_response' => [
                    'checkout_url' => $chapaResponse->checkoutUrl,
                    'status' => $chapaResponse->status,
                ],
            ]);
            $payment->save();

            return $chapaResponse;
        });
    }
}

