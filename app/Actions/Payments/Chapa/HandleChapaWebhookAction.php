<?php

namespace App\Actions\Payments\Chapa;

use App\Enums\PaymentTransactionStatus;
use App\Models\Payment;
use App\Services\Chapa\ChapaClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleChapaWebhookAction
{
    public function __construct(
        private ChapaClient $chapaClient
    ) {
    }

    /**
     * Handle Chapa webhook - idempotently update payment status
     * Note: Webhook signature verification should be added based on Chapa docs
     */
    public function execute(array $webhookData): void
    {
        $txRef = $webhookData['tx_ref'] ?? null;

        if (!$txRef) {
            Log::warning('Chapa webhook missing tx_ref', ['data' => $webhookData]);
            return;
        }

        DB::transaction(function () use ($txRef, $webhookData) {
            $payment = Payment::where('transaction_reference', $txRef)->first();

            if (!$payment) {
                Log::warning('Chapa webhook: payment not found', ['tx_ref' => $txRef]);
                return;
            }

            // Idempotency: only update if still pending
            if ($payment->status !== PaymentTransactionStatus::PENDING) {
                Log::info('Chapa webhook: payment already processed', [
                    'tx_ref' => $txRef,
                    'current_status' => $payment->status->value,
                ]);
                return;
            }

            $chapaStatus = strtolower($webhookData['status'] ?? 'unknown');
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
                'chapa_webhook' => $webhookData,
                'webhook_processed_at' => now()->toIso8601String(),
            ]);

            $payment->save();

            $payment->reservation->calculatePaymentStatus();

            Log::info('Chapa webhook processed', [
                'tx_ref' => $txRef,
                'new_status' => $newStatus->value,
            ]);
        });
    }
}

