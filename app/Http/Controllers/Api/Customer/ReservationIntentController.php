<?php

namespace App\Http\Controllers\Api\Customer;

use App\Actions\Payments\Chapa\CreateReservationIntentAction;
use App\Actions\Payments\Chapa\InitiatePaymentForIntentAction;
use App\DTO\Payments\Chapa\CreatePaymentForIntentDto;
use App\DTO\Payments\Chapa\CreateReservationIntentDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreReservationIntentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

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
    public function store(StoreReservationIntentRequest $request): JsonResponse
    {
        $validated = $request->validated();
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

        // Ensure the return URL always includes tx_ref so frontend can identify the payment on callback
        $baseReturnUrl = $request->input('return_url', config('app.frontend_url') . '/payment/return');
        $returnUrl = $this->appendQueryParam($baseReturnUrl, 'tx_ref', $txRef);

        $paymentDto = new CreatePaymentForIntentDto(
            reservationIntentId: $intent->id,
            txRef: $txRef,
            amount: (float) $intent->total_amount,
            currency: $intent->currency,
            callbackUrl: route('api.webhooks.chapa'),
            returnUrl: $returnUrl,
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

    private function appendQueryParam(string $url, string $key, string $value): string
    {
        $parts = parse_url($url);
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query[$key] = $value;
        $parts['query'] = http_build_query($query);

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? null;
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parts['path'] ?? '';
        $queryString = $parts['query'] ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        // Absolute URL
        if ($scheme && $host) {
            return "{$scheme}://{$user}{$pass}{$host}{$port}{$path}{$queryString}{$fragment}";
        }

        // Relative URL
        return "{$path}{$queryString}{$fragment}";
    }
}
