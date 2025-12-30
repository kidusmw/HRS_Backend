<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationOverride;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OverrideController extends Controller
{
    public function index(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        $perPage = (int) $request->input('per_page', 10);
        $bookingId = $request->input('booking_id');

        $query = ReservationOverride::query()
            ->with(['reservation.room', 'manager'])
            ->whereHas('reservation.room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            });

        if ($bookingId) {
            $query->whereHas('reservation', function ($q) use ($bookingId) {
                $q->where('id', $bookingId);
            });
        }

        $overrides = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($overrides);
    }

    public function store(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:reservations,id'],
            'new_status' => ['required', Rule::in(['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'])],
            'note' => ['nullable', 'string'],
        ]);

        $reservation = Reservation::with('room')
            ->where('id', $data['booking_id'])
            ->firstOrFail();

        if (!$reservation->room || $reservation->room->hotel_id !== $hotelId) {
            return response()->json(['message' => 'Reservation not in your hotel'], 403);
        }

        $oldStatus = $reservation->status;

        // Actually update the reservation status
        $reservation->status = $data['new_status'];
        $reservation->save();

        // Create override record for audit trail
        $override = ReservationOverride::create([
            'reservation_id' => $reservation->id,
            'manager_id' => $manager->id,
            'new_status' => $data['new_status'],
            'note' => $data['note'] ?? null,
        ]);

        // Create audit log for the override
        \App\Services\AuditLogger::log('reservation.override', $manager, $hotelId, [
            'reservation_id' => $reservation->id,
            'old_status' => $oldStatus,
            'new_status' => $data['new_status'],
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['data' => $override], 201);
    }
}

