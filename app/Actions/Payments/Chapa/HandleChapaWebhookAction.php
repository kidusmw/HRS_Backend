<?php

namespace App\Actions\Payments\Chapa;

use App\DTO\Payments\Chapa\WebhookEventDto;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoomStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationIntent;
use App\Models\Room;
use App\Services\Chapa\ChapaClient;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleChapaWebhookAction
{
    public function __construct(
        private ChapaClient $chapaClient
    ) {
    }

    /**
     * Authoritative webhook handler: verify signature, verify via Chapa, confirm atomically
     */
    public function execute(array $webhookData, array $signatures = [], ?string $rawBody = null): void
    {
        if (!$this->verifySignature($signatures, $rawBody)) {
            Log::warning('Chapa webhook: invalid signature', [
                'has_signatures' => !empty($signatures),
            ]);

            throw new HttpResponseException(
                response()->json([
                    'message' => 'Invalid webhook signature',
                ], 401)
            );
        }

        $txRef = $webhookData['tx_ref'] ?? null;
        if (!$txRef) {
            Log::warning('Chapa webhook missing tx_ref', ['data' => $webhookData]);
            return;
        }

        // Find payment by transaction reference
        $payment = Payment::where('transaction_reference', $txRef)->first();
        if (!$payment) {
            Log::warning('Chapa webhook: payment not found', ['tx_ref' => $txRef]);
            return;
        }

        // Idempotency: if already paid/refunded, do nothing
        if (in_array($payment->status, [
            PaymentTransactionStatus::PAID,
            PaymentTransactionStatus::COMPLETED,
            PaymentTransactionStatus::REFUNDED,
        ])) {
            Log::info('Chapa webhook: payment already processed', [
                'tx_ref' => $txRef,
                'current_status' => $payment->status->value,
            ]);
            return;
        }

        // Authoritative: verify via Chapa verify endpoint
        $verification = $this->chapaClient->verifyPayment($txRef);
        $chapaStatus = strtolower($verification->chapaStatus);

        // Validate amount and currency match
        $intent = $payment->reservationIntent()->with('user')->first();
        if (!$intent) {
            Log::error('Chapa webhook: payment has no reservation intent', ['payment_id' => $payment->id]);
            return;
        }

        $verifiedAmount = (float) ($verification->raw['data']['amount'] ?? 0);
        $verifiedCurrency = $verification->raw['data']['currency'] ?? '';

        if (abs($verifiedAmount - (float) $intent->total_amount) > 0.01) {
            Log::error('Chapa webhook: amount mismatch', [
                'expected' => $intent->total_amount,
                'verified' => $verifiedAmount,
            ]);
            $payment->status = PaymentTransactionStatus::FAILED;
            $payment->meta = array_merge($payment->meta ?? [], [
                'webhook_error' => 'Amount mismatch',
                'webhook_data' => $webhookData,
            ]);
            $payment->save();
            return;
        }

        if (strtoupper($verifiedCurrency) !== strtoupper($intent->currency)) {
            Log::error('Chapa webhook: currency mismatch', [
                'expected' => $intent->currency,
                'verified' => $verifiedCurrency,
            ]);
            $payment->status = PaymentTransactionStatus::FAILED;
            $payment->meta = array_merge($payment->meta ?? [], [
                'webhook_error' => 'Currency mismatch',
                'webhook_data' => $webhookData,
            ]);
            $payment->save();
            return;
        }

        // Atomically confirm: payment paid, intent confirmed, create reservation, assign room
        DB::transaction(function () use ($payment, $intent, $chapaStatus, $verification, $webhookData) {
            // Determine payment status
            $newPaymentStatus = match ($chapaStatus) {
                'success', 'successful', 'completed' => PaymentTransactionStatus::PAID,
                'failed', 'cancelled' => PaymentTransactionStatus::FAILED,
                default => PaymentTransactionStatus::PENDING,
            };

            if ($newPaymentStatus === PaymentTransactionStatus::PAID) {
                $payment->status = $newPaymentStatus;
                $payment->paid_at = now();
                $payment->meta = array_merge($payment->meta ?? [], [
                    'chapa_verification' => $verification->raw,
                    'webhook_data' => $webhookData,
                    'verified_at' => now()->toIso8601String(),
                ]);
                $payment->save();

                // Mark intent as confirmed
                $intent->status = 'confirmed';
                $intent->save();

                // Create reservation and assign room
                $room = $this->assignRoom($intent);
                if (!$room) {
                    throw new \Exception('No available room found for reservation intent');
                }

                $reservation = Reservation::create([
                    'room_id' => $room->id,
                    'user_id' => $intent->user_id,
                    'guest_name' => $intent->user->name,
                    'guest_email' => $intent->user->email,
                    'guest_phone' => $intent->user->phone_number,
                    'check_in' => $intent->check_in,
                    'check_out' => $intent->check_out,
                    'status' => 'confirmed',
                    'total_amount' => $intent->total_amount,
                    'payment_status' => PaymentStatus::PAID,
                    'guests' => 1, // Default, can be enhanced
                ]);

                // Link payment to reservation
                $payment->reservation_id = $reservation->id;
                $payment->save();

                Log::info('Chapa webhook: reservation confirmed', [
                    'tx_ref' => $payment->transaction_reference,
                    'reservation_id' => $reservation->id,
                    'room_id' => $room->id,
                ]);
            } else {
                // Payment failed
                $payment->status = $newPaymentStatus;
                $intent->status = 'failed';
                $payment->meta = array_merge($payment->meta ?? [], [
                    'chapa_verification' => $verification->raw,
                    'webhook_data' => $webhookData,
                ]);
                $payment->save();
                $intent->save();
            }
        });
    }

    /**
     * Assign an available room for the intent (with locking to prevent double booking)
     */
    private function assignRoom(ReservationIntent $intent): ?Room
    {
        return DB::transaction(function () use ($intent) {
            // Lock and select available room
            $room = Room::where('hotel_id', $intent->hotel_id)
                ->where('type', $intent->room_type)
                ->where('status', RoomStatus::AVAILABLE)
                ->lockForUpdate()
                ->first();

            if (!$room) {
                return null;
            }

            // Double-check no overlapping reservations
            $hasOverlap = Reservation::where('room_id', $room->id)
                ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                ->where(function ($q) use ($intent) {
                    $q->where(function ($sub) use ($intent) {
                        $sub->whereBetween('check_in', [$intent->check_in->toDateString(), $intent->check_out->toDateString()]);
                    })
                    ->orWhere(function ($sub) use ($intent) {
                        $sub->whereBetween('check_out', [$intent->check_in->toDateString(), $intent->check_out->toDateString()]);
                    })
                    ->orWhere(function ($sub) use ($intent) {
                        $sub->where('check_in', '<=', $intent->check_in->toDateString())
                            ->where('check_out', '>=', $intent->check_out->toDateString());
                    });
                })
                ->exists();

            if ($hasOverlap) {
                return null;
            }

            return $room;
        });
    }

    private function verifySignature(array $signatures, ?string $rawBody): bool
    {
        $secret = config('services.chapa.webhook_secret');
        if (empty($secret)) {
            Log::error('Chapa webhook secret is not configured (CHAPA_WEBHOOK_SECRET)');
            return false;
        }

        if (empty($signatures) || $rawBody === null) {
            return false;
        }

        $expectedRaw = hash_hmac('sha256', $rawBody, $secret);
        $expectedTrimmed = hash_hmac('sha256', trim($rawBody), $secret);

        foreach ($signatures as $sig) {
            $sig = trim((string) $sig);

            // Handle possible prefix format: sha256=<hex>
            if (str_starts_with($sig, 'sha256=')) {
                $sig = substr($sig, 7);
            }

            if (hash_equals($expectedRaw, $sig) || hash_equals($expectedTrimmed, $sig)) {
                return true;
            }
        }

        return false;
    }
}
