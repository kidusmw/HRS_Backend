<?php

namespace App\Services\Chapa;

use App\DTO\Payments\Chapa\InitiateChapaPaymentRequestDto;
use App\DTO\Payments\Chapa\InitiateChapaPaymentResponseDto;
use App\DTO\Payments\Chapa\VerifyChapaPaymentResponseDto;
use App\Exceptions\Payments\ChapaRequestFailedException;
use App\Exceptions\Payments\ChapaVerificationFailedException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChapaClient
{
    private PendingRequest $client;
    private string $baseUrl;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.chapa.base_url', 'https://api.chapa.co/v1');
        $this->secretKey = config('services.chapa.secret_key');

        if (empty($this->secretKey)) {
            throw new \RuntimeException('Chapa secret key is not configured');
        }

        $this->client = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    /**
     * Initiate a payment with Chapa
     *
     * @throws ChapaRequestFailedException
     */
    public function initiatePayment(InitiateChapaPaymentRequestDto $dto): InitiateChapaPaymentResponseDto
    {
        $txRef = 'TXN-' . Str::random(16) . '-' . time();

        $payload = [
            'amount' => $dto->amount,
            'currency' => $dto->currency,
            'email' => $dto->customerEmail,
            'first_name' => $dto->customerName,
            'phone_number' => $dto->customerPhone ?? '',
            'tx_ref' => $txRef,
            'callback_url' => $dto->callbackUrl,
            'return_url' => $dto->returnUrl,
        ];

        try {
            $response = $this->client->post("{$this->baseUrl}/transaction/initialize", $payload);

            if (!$response->successful()) {
                $errorMessage = $response->json('message') ?? 'Failed to initiate Chapa payment';
                throw new ChapaRequestFailedException(
                    $errorMessage,
                    $response->json() ?? []
                );
            }

            $data = $response->json('data');
            $checkoutUrl = $data['checkout_url'] ?? null;

            if (!$checkoutUrl) {
                throw new ChapaRequestFailedException(
                    'Chapa response missing checkout_url',
                    $response->json() ?? []
                );
            }

            return new InitiateChapaPaymentResponseDto(
                checkoutUrl: $checkoutUrl,
                txRef: $txRef,
                status: $data['status'] ?? 'pending'
            );
        } catch (ChapaRequestFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ChapaRequestFailedException(
                'Failed to communicate with Chapa API: ' . $e->getMessage(),
                [],
                $e
            );
        }
    }

    /**
     * Verify a payment transaction
     *
     * @throws ChapaVerificationFailedException
     */
    public function verifyPayment(string $txRef): VerifyChapaPaymentResponseDto
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/transaction/verify/{$txRef}");

            if (!$response->successful()) {
                $errorMessage = $response->json('message') ?? 'Failed to verify Chapa payment';
                throw new ChapaVerificationFailedException(
                    $errorMessage,
                    $response->json() ?? []
                );
            }

            $data = $response->json('data');
            $status = $data['status'] ?? 'unknown';

            return new VerifyChapaPaymentResponseDto(
                txRef: $txRef,
                chapaStatus: $status,
                raw: $response->json() ?? []
            );
        } catch (ChapaVerificationFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ChapaVerificationFailedException(
                'Failed to communicate with Chapa API: ' . $e->getMessage(),
                [],
                $e
            );
        }
    }

    /**
     * Refund a payment (if supported by Chapa)
     * Note: Check Chapa documentation for refund endpoint availability
     *
     * @throws ChapaRequestFailedException
     */
    public function refund(string $txRef, string $reason): VerifyChapaPaymentResponseDto
    {
        // Chapa may not have a direct refund endpoint
        // This is a placeholder - adjust based on actual Chapa API documentation
        try {
            $response = $this->client->post("{$this->baseUrl}/transaction/refund", [
                'tx_ref' => $txRef,
                'reason' => $reason,
            ]);

            if (!$response->successful()) {
                $errorMessage = $response->json('message') ?? 'Failed to refund Chapa payment';
                throw new ChapaRequestFailedException(
                    $errorMessage,
                    $response->json() ?? []
                );
            }

            $data = $response->json('data');
            $status = $data['status'] ?? 'refunded';

            return new VerifyChapaPaymentResponseDto(
                txRef: $txRef,
                chapaStatus: $status,
                raw: $response->json() ?? []
            );
        } catch (ChapaRequestFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ChapaRequestFailedException(
                'Failed to communicate with Chapa API for refund: ' . $e->getMessage(),
                [],
                $e
            );
        }
    }
}

