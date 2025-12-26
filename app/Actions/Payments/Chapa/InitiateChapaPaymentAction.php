<?php

namespace App\Actions\Payments\Chapa;

use App\DTO\Payments\Chapa\InitiateChapaPaymentRequestDto;
use App\DTO\Payments\Chapa\InitiateChapaPaymentResponseDto;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\Payments\ReservationAlreadyPaidException;
use App\Models\Reservation;
use App\Services\Chapa\ChapaClient;
use Illuminate\Support\Facades\DB;

class InitiateChapaPaymentAction
{
    public function __construct(
        private ChapaClient $chapaClient
    ) {
    }

    /**
     * @throws ReservationAlreadyPaidException
     */
    public function execute(InitiateChapaPaymentRequestDto $dto): InitiateChapaPaymentResponseDto
    {
        $reservation = Reservation::findOrFail($dto->reservationId);

        if ($reservation->payment_status->value === 'paid') {
            throw new ReservationAlreadyPaidException();
        }

        return DB::transaction(function () use ($dto, $reservation) {
            $chapaResponse = $this->chapaClient->initiatePayment($dto);

            $payment = $reservation->payments()->create([
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'method' => PaymentMethod::CHAPA,
                'status' => PaymentTransactionStatus::PENDING,
                'transaction_reference' => $chapaResponse->txRef,
                'meta' => [
                    'chapa_init_response' => [
                        'status' => $chapaResponse->status,
                        'checkout_url' => $chapaResponse->checkoutUrl,
                    ],
                ],
            ]);

            return $chapaResponse;
        });
    }
}

