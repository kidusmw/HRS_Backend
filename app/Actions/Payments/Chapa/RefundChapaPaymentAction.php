<?php

namespace App\Actions\Payments\Chapa;

use App\DTO\Payments\Chapa\RefundChapaPaymentRequestDto;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\Payments\PaymentNotCompletedException;
use App\Exceptions\Payments\PaymentRefundWindowExpiredException;
use App\Models\Payment;
use App\Services\Chapa\ChapaClient;
use Illuminate\Support\Facades\DB;

class RefundChapaPaymentAction
{
    public function __construct(
        private ChapaClient $chapaClient
    ) {
    }

    /**
     * @throws PaymentNotCompletedException
     * @throws PaymentRefundWindowExpiredException
     */
    public function execute(RefundChapaPaymentRequestDto $dto): void
    {
        $payment = Payment::findOrFail($dto->paymentId);

        if ($payment->status !== PaymentTransactionStatus::COMPLETED) {
            throw new PaymentNotCompletedException();
        }

        if (!$payment->paid_at) {
            throw new PaymentRefundWindowExpiredException('Payment completion time is not recorded');
        }

        $refundDeadline = $payment->paid_at->addHours(24);
        if (now()->isAfter($refundDeadline)) {
            throw new PaymentRefundWindowExpiredException();
        }

        DB::transaction(function () use ($payment, $dto) {
            $this->chapaClient->refund($payment->transaction_reference, $dto->reason);

            $payment->status = PaymentTransactionStatus::REFUNDED;
            $payment->meta = array_merge($payment->meta ?? [], [
                'refunded_at' => now()->toIso8601String(),
                'refund_reason' => $dto->reason,
            ]);
            $payment->save();

            $payment->reservation->calculatePaymentStatus();
        });
    }
}

