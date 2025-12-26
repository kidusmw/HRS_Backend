<?php

namespace App\Actions\Payments\Chapa;

use App\DTO\Payments\Chapa\VerifyChapaPaymentResponseDto;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\Payments\ChapaVerificationFailedException;
use App\Models\Payment;
use App\Services\Chapa\ChapaClient;
use Illuminate\Support\Facades\DB;

class VerifyChapaPaymentAction
{
    public function __construct(
        private ChapaClient $chapaClient
    ) {
    }

    /**
     * @throws ChapaVerificationFailedException
     */
    public function execute(string $txRef): VerifyChapaPaymentResponseDto
    {
        $verificationResponse = $this->chapaClient->verifyPayment($txRef);

        return DB::transaction(function () use ($verificationResponse, $txRef) {
            $payment = Payment::where('transaction_reference', $txRef)->firstOrFail();

            $chapaStatus = strtolower($verificationResponse->chapaStatus);
            $newStatus = match ($chapaStatus) {
                'success', 'successful', 'completed' => PaymentTransactionStatus::COMPLETED,
                'failed', 'cancelled' => PaymentTransactionStatus::FAILED,
                default => PaymentTransactionStatus::PENDING,
            };

            $payment->status = $newStatus;

            if ($newStatus === PaymentTransactionStatus::COMPLETED) {
                $payment->paid_at = now();
            }

            $payment->meta = array_merge($payment->meta ?? [], [
                'chapa_verification' => $verificationResponse->raw,
                'verified_at' => now()->toIso8601String(),
            ]);

            $payment->save();

            $payment->reservation->calculatePaymentStatus();

            return $verificationResponse;
        });
    }
}

