<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\PaymentTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReservationController extends Controller
{
    /**
     * Get authenticated customer's reservations
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reservations = Reservation::where('user_id', $user->id)
            ->with(['room.hotel', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $reservations->map(function ($reservation) {
            // Calculate amount paid from completed/paid payments
            $amountPaid = (float) $reservation->payments
                ->whereIn('status', [
                    PaymentTransactionStatus::PAID,
                    PaymentTransactionStatus::COMPLETED,
                ])
                ->sum('amount');

            return [
                'id' => $reservation->id,
                'hotelId' => $reservation->room?->hotel?->id ?? null,
                'hotelName' => $reservation->room?->hotel?->name ?? 'Unknown Hotel',
                'roomType' => $reservation->room?->type ?? 'Unknown',
                'roomNumber' => $reservation->room?->id ?? null,
                'checkIn' => $reservation->check_in?->format('Y-m-d'),
                'checkOut' => $reservation->check_out?->format('Y-m-d'),
                'status' => $reservation->status,
                'totalAmount' => (float) ($reservation->total_amount ?? 0),
                'amountPaid' => $amountPaid,
                'currency' => $reservation->payments->first()?->currency ?? 'ETB',
                'paymentStatus' => $reservation->payment_status?->value ?? 'pending',
                'createdAt' => $reservation->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
