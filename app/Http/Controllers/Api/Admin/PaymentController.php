<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Http\Request;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Get all payments for the admin's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns a paginated list of real payment transactions (one row per payment).
     * Payments are scoped by hotel through reservation -> room -> hotel.
     * Supports filtering by status and search.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Query payments scoped by hotel (through reservation -> room -> hotel)
        $query = Payment::whereHas('reservation.room', function ($q) use ($hotelId) {
            $q->where('hotel_id', $hotelId);
        })
        ->with(['reservation.room', 'reservation.user', 'collector']);

        // Search by guest name, email, reservation id, or transaction reference
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('reservation', function ($resQuery) use ($search) {
                    $resQuery->where('id', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                })
                ->orWhere('transaction_reference', 'like', "%{$search}%");
            });
        }

        // Filter by payment status
        if ($status = $request->string('status')->toString()) {
            $statusEnum = PaymentTransactionStatus::tryFrom($status);
            if ($statusEnum) {
                $query->where('status', $statusEnum);
            }
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        // Transform payments to response format
        $transformedPayments = $payments->getCollection()->map(function ($payment) {
            return $this->transformPaymentToResponse($payment);
        });

        return response()->json([
            'data' => $transformedPayments,
            'links' => $payments->linkCollection(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'from' => $payments->firstItem(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'to' => $payments->lastItem(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Get a specific payment by ID
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Get payment for this hotel
        $payment = Payment::whereHas('reservation.room', function ($q) use ($hotelId) {
            $q->where('hotel_id', $hotelId);
        })
        ->with(['reservation.room', 'reservation.user', 'collector'])
        ->findOrFail($id);

        return response()->json([
            'data' => $this->transformPaymentToResponse($payment)
        ]);
    }

    /**
     * Transform a payment to response format
     * 
     * @param Payment $payment
     * @return array
     */
    private function transformPaymentToResponse(Payment $payment): array
    {
        $reservation = $payment->reservation;
        
        // Generate reservation number
        $reservationNumber = 'RES-' . date('Y', strtotime($reservation->created_at)) . '-' . str_pad($reservation->id, 3, '0', STR_PAD_LEFT);

        return [
            'id' => $payment->id,
            'reservationId' => $reservation->id,
            'reservationNumber' => $reservationNumber,
            'guestName' => $reservation->guest_name ?? $reservation->user?->name ?? 'Guest',
            'guestEmail' => $reservation->guest_email ?? $reservation->user?->email,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'paymentMethod' => is_object($payment->method) ? $payment->method->value : (string) $payment->method,
            'status' => is_object($payment->status) ? $payment->status->value : (string) $payment->status,
            'transactionReference' => $payment->transaction_reference,
            'collectedBy' => $payment->collector?->name,
            'paidAt' => $payment->paid_at?->toIso8601String(),
            'createdAt' => $payment->created_at->toIso8601String(),
        ];
    }
}

