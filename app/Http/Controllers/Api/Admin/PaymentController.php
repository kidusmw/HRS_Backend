<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Get all payments for the admin's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns a paginated list of payments for reservations in the hotel.
     * Payments are derived from reservations (through rooms).
     * Supports filtering by status and search.
     * 
     * Note: This is a read-only view. Payments are managed through reservations.
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

        // Get reservations for this hotel through rooms
        $query = Reservation::whereHas('room', function ($q) use ($hotelId) {
            $q->where('hotel_id', $hotelId);
        })
        ->with(['room', 'user']);

        // Search by guest name, email, or reservation number
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Filter by payment status (derived from reservation status)
        // Note: This is a simplified mapping. In a real system, payments would have their own status.
        if ($status = $request->string('status')->toString()) {
            // Map payment status to reservation status
            $statusMap = [
                'completed' => 'confirmed',
                'pending' => 'pending',
                'failed' => 'cancelled',
                'refunded' => 'cancelled',
            ];
            if (isset($statusMap[$status])) {
                $query->where('status', $statusMap[$status]);
            }
        }

        $reservations = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        // Transform reservations to payment format
        $transformedPayments = $reservations->getCollection()->map(function ($reservation) {
            return $this->transformReservationToPayment($reservation);
        });

        return response()->json([
            'data' => $transformedPayments,
            'links' => $reservations->linkCollection(),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'from' => $reservations->firstItem(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'to' => $reservations->lastItem(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    /**
     * Get a specific payment by ID
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * Note: Payment ID is actually the reservation ID in this implementation.
     * In a real system with a Payment model, this would query the payments table.
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

        // Get reservation (payment) for this hotel
        $reservation = Reservation::whereHas('room', function ($q) use ($hotelId) {
            $q->where('hotel_id', $hotelId);
        })
        ->with(['room', 'user'])
        ->findOrFail($id);

        return response()->json([
            'data' => $this->transformReservationToPayment($reservation)
        ]);
    }

    /**
     * Get available payment methods from system settings
     * Payment methods are configured by the super admin
     * 
     * @return array
     */
    private function getAvailablePaymentMethods(): array
    {
        $paymentMethodsJson = SystemSetting::getValue('payment_methods');
        
        if ($paymentMethodsJson) {
            $methods = json_decode($paymentMethodsJson, true);
            if (is_array($methods) && !empty($methods)) {
                return $methods;
            }
        }
        
        // Default payment methods if not configured
        return ['online', 'credit_card', 'debit_card', 'cash', 'bank_transfer'];
    }

    /**
     * Get default payment method (first available from system settings)
     * 
     * @return string
     */
    private function getDefaultPaymentMethod(): string
    {
        $methods = $this->getAvailablePaymentMethods();
        return $methods[0] ?? 'online';
    }

    /**
     * Transform a reservation to payment format
     * This is a temporary solution until a Payment model is created.
     * 
     * @param Reservation $reservation
     * @return array
     */
    private function transformReservationToPayment(Reservation $reservation): array
    {
        // Calculate payment amount from room price and nights
        $nights = max(1, $reservation->check_in->diffInDays($reservation->check_out));
        $amount = (float) ($reservation->room->price * $nights);

        // Map reservation status to payment status
        $paymentStatusMap = [
            'confirmed' => 'completed',
            'pending' => 'pending',
            'cancelled' => 'refunded',
            'completed' => 'completed',
        ];
        $paymentStatus = $paymentStatusMap[$reservation->status] ?? 'pending';

        // Generate reservation number
        $reservationNumber = 'RES-' . date('Y', strtotime($reservation->created_at)) . '-' . str_pad($reservation->id, 3, '0', STR_PAD_LEFT);

        // Generate transaction ID
        $transactionId = 'TXN-' . date('Ymd', strtotime($reservation->created_at)) . '-' . str_pad($reservation->id, 3, '0', STR_PAD_LEFT);

        // Get currency from system settings (set by super admin)
        $currency = SystemSetting::getValue('default_currency', 'USD');

        // Get payment method from system settings (first available method)
        // Note: In the future, when reservations store payment_method, use that instead
        $paymentMethod = $this->getDefaultPaymentMethod();

        return [
            'id' => $reservation->id, // Using reservation ID as payment ID
            'reservationId' => $reservation->id,
            'reservationNumber' => $reservationNumber,
            'guestName' => $reservation->user?->name ?? 'Guest',
            'guestEmail' => $reservation->user?->email,
            'amount' => $amount,
            'currency' => $currency, // From system settings (set by super admin)
            'paymentMethod' => $paymentMethod, // From system settings (set by super admin)
            'status' => $paymentStatus,
            'transactionId' => $transactionId,
            'paidAt' => $reservation->created_at->toIso8601String(),
            'createdAt' => $reservation->created_at->toIso8601String(),
        ];
    }
}

