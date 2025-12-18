<?php

namespace App\Http\Controllers\Api\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Enums\UserRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    /**
     * Get all reservations for the receptionist's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $receptionist = $request->user();
        $hotelId = $receptionist->hotel_id;

        Log::info('Receptionist reservations list accessed', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
        ]);

        if (!$hotelId) {
            Log::warning('Receptionist reservations list accessed without hotel_id', [
                'receptionist_id' => $receptionist->id,
            ]);
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $perPage = (int) $request->input('per_page', 10);
        $status = $request->input('status');
        $dateFrom = $request->input('date_from') ?? $request->input('start');
        $dateTo = $request->input('date_to') ?? $request->input('end');
        $search = $request->input('search');

        $query = Reservation::query()
            ->with(['room', 'user'])
            ->whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            });

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('check_in', '>=', Carbon::parse($dateFrom)->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('check_in', '<=', Carbon::parse($dateTo)->toDateString());
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('room', function ($sub) use ($search) {
                    $sub->where('type', 'like', "%{$search}%");
                })->orWhere('id', $search);
            });
        }

        $reservations = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($reservations);
    }

    /**
     * Create a walk-in reservation
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $receptionist = $request->user();
        $hotelId = $receptionist->hotel_id;

        Log::info('Receptionist walk-in reservation creation attempted', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
        ]);

        if (!$hotelId) {
            Log::warning('Receptionist walk-in reservation without hotel_id', [
                'receptionist_id' => $receptionist->id,
            ]);
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'guestName' => 'required|string|max:255',
            'guestEmail' => 'nullable|email|max:255',
            'guestPhone' => 'nullable|string|max:255',
            'roomNumber' => 'required|integer|exists:rooms,id',
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'specialRequests' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            Log::warning('Receptionist walk-in reservation validation failed', [
                'receptionist_id' => $receptionist->id,
                'hotel_id' => $hotelId,
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify room belongs to hotel
        $room = Room::where('id', $request->input('roomNumber'))
            ->where('hotel_id', $hotelId)
            ->first();

        if (!$room) {
            Log::warning('Receptionist attempted walk-in reservation for invalid room', [
                'receptionist_id' => $receptionist->id,
                'hotel_id' => $hotelId,
                'room_id' => $request->input('roomNumber'),
            ]);
            return response()->json([
                'message' => 'Room not found or does not belong to your hotel'
            ], 404);
        }

        // Check if room is available
        if (!$room->is_available) {
            Log::warning('Receptionist attempted walk-in reservation for unavailable room', [
                'receptionist_id' => $receptionist->id,
                'hotel_id' => $hotelId,
                'room_id' => $room->id,
            ]);
            return response()->json([
                'message' => 'Room is not available'
            ], 422);
        }

        // Check for date conflicts
        $checkIn = Carbon::parse($request->input('checkIn'));
        $checkOut = Carbon::parse($request->input('checkOut'));
        
        $conflictingReservation = Reservation::where('room_id', $room->id)
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut])
                  ->orWhereBetween('check_out', [$checkIn, $checkOut])
                  ->orWhere(function ($sub) use ($checkIn, $checkOut) {
                      $sub->where('check_in', '<=', $checkIn)
                          ->where('check_out', '>=', $checkOut);
                  });
            })
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->exists();

        if ($conflictingReservation) {
            Log::warning('Receptionist walk-in reservation date conflict detected', [
                'receptionist_id' => $receptionist->id,
                'hotel_id' => $hotelId,
                'room_id' => $room->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
            ]);
            return response()->json([
                'message' => 'Room is already booked for the selected dates'
            ], 422);
        }

        // Find or create guest user
        $guestEmail = $request->input('guestEmail');
        $guestUser = null;

        if ($guestEmail) {
            $guestUser = User::where('email', $guestEmail)->first();
        }

        if (!$guestUser) {
            // Create guest user
            $guestUser = User::create([
                'name' => $request->input('guestName'),
                'email' => $guestEmail ?: 'guest_' . Str::random(8) . '@walkin.local',
                'password' => bcrypt(Str::random(32)), // Random password, guest won't login
                'role' => UserRole::CLIENT,
                'email_verified_at' => $guestEmail ? now() : null,
                'active' => true,
                'phone_number' => $request->input('guestPhone'),
            ]);

            Log::info('Guest user created for walk-in reservation', [
                'receptionist_id' => $receptionist->id,
                'hotel_id' => $hotelId,
                'guest_user_id' => $guestUser->id,
                'guest_email' => $guestUser->email,
            ]);
        } else {
            // Update guest info if provided
            if ($request->input('guestName') && $guestUser->name !== $request->input('guestName')) {
                $guestUser->name = $request->input('guestName');
            }
            if ($request->input('guestPhone') && $guestUser->phone_number !== $request->input('guestPhone')) {
                $guestUser->phone_number = $request->input('guestPhone');
            }
            $guestUser->save();
        }

        // Create reservation
        $reservation = Reservation::create([
            'room_id' => $room->id,
            'user_id' => $guestUser->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => 'confirmed', // Walk-ins are automatically confirmed
            'guests' => 1, // Default, can be updated if needed
            'special_requests' => $request->input('specialRequests'),
        ]);

        Log::info('Receptionist walk-in reservation created successfully', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'guest_user_id' => $guestUser->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
        ]);

        $reservation->load(['room', 'user']);

        return response()->json([
            'message' => 'Walk-in reservation created successfully',
            'data' => $reservation
        ], 201);
    }

    /**
     * Confirm a pending reservation
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request, int $id)
    {
        $receptionist = $request->user();
        $hotelId = $receptionist->hotel_id;

        Log::info('Receptionist reservation confirm attempted', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $id,
        ]);

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $reservation = Reservation::with('room', 'user')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->findOrFail($id);

        $oldStatus = $reservation->status;

        if ($reservation->status !== 'pending') {
            Log::warning('Receptionist attempted to confirm non-pending reservation', [
                'receptionist_id' => $receptionist->id,
                'reservation_id' => $id,
                'current_status' => $reservation->status,
            ]);
            return response()->json([
                'message' => 'Only pending reservations can be confirmed'
            ], 422);
        }

        $reservation->status = 'confirmed';
        $reservation->save();

        Log::info('Receptionist reservation confirmed successfully', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => 'confirmed',
            'room_id' => $reservation->room_id,
            'guest_user_id' => $reservation->user_id,
        ]);

        $reservation->load(['room', 'user']);

        return response()->json([
            'message' => 'Reservation confirmed successfully',
            'data' => $reservation
        ]);
    }

    /**
     * Cancel a reservation
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request, int $id)
    {
        $receptionist = $request->user();
        $hotelId = $receptionist->hotel_id;

        Log::info('Receptionist reservation cancel attempted', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $id,
        ]);

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $reservation = Reservation::with('room', 'user')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->findOrFail($id);

        $oldStatus = $reservation->status;

        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            Log::warning('Receptionist attempted to cancel reservation in invalid status', [
                'receptionist_id' => $receptionist->id,
                'reservation_id' => $id,
                'current_status' => $reservation->status,
            ]);
            return response()->json([
                'message' => 'Only pending or confirmed reservations can be cancelled'
            ], 422);
        }

        $reservation->status = 'cancelled';
        $reservation->save();

        Log::info('Receptionist reservation cancelled successfully', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => 'cancelled',
            'room_id' => $reservation->room_id,
            'guest_user_id' => $reservation->user_id,
        ]);

        $reservation->load(['room', 'user']);

        return response()->json([
            'message' => 'Reservation cancelled successfully',
            'data' => $reservation
        ]);
    }

    /**
     * Check-in a guest
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkIn(Request $request, int $id)
    {
        $receptionist = $request->user();
        $hotelId = $receptionist->hotel_id;

        Log::info('Receptionist check-in attempted', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $id,
        ]);

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $reservation = Reservation::with('room', 'user')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->findOrFail($id);

        $oldStatus = $reservation->status;

        if ($reservation->status !== 'confirmed') {
            Log::warning('Receptionist attempted check-in for non-confirmed reservation', [
                'receptionist_id' => $receptionist->id,
                'reservation_id' => $id,
                'current_status' => $reservation->status,
            ]);
            return response()->json([
                'message' => 'Only confirmed reservations can be checked in'
            ], 422);
        }

        // Check if check-in date is today or in the past
        $checkInDate = Carbon::parse($reservation->check_in);
        if ($checkInDate->isFuture()) {
            Log::warning('Receptionist attempted early check-in', [
                'receptionist_id' => $receptionist->id,
                'reservation_id' => $id,
                'check_in_date' => $checkInDate->toDateString(),
            ]);
            return response()->json([
                'message' => 'Cannot check in before the check-in date'
            ], 422);
        }

        $reservation->status = 'checked_in';
        $reservation->save();

        // Update room status to occupied (if room has is_available field)
        $room = $reservation->room;
        if ($room) {
            $oldRoomStatus = $room->is_available;
            $room->is_available = false;
            $room->save();

            Log::info('Room status updated during check-in', [
                'receptionist_id' => $receptionist->id,
                'hotel_id' => $hotelId,
                'room_id' => $room->id,
                'old_room_status' => $oldRoomStatus ? 'available' : 'unavailable',
                'new_room_status' => 'occupied',
            ]);
        }

        Log::info('Receptionist check-in completed successfully', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => 'checked_in',
            'room_id' => $reservation->room_id,
            'guest_user_id' => $reservation->user_id,
        ]);

        $reservation->load(['room', 'user']);

        return response()->json([
            'message' => 'Guest checked in successfully',
            'data' => $reservation
        ]);
    }

    /**
     * Check-out a guest
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkOut(Request $request, int $id)
    {
        $receptionist = $request->user();
        $hotelId = $receptionist->hotel_id;

        Log::info('Receptionist check-out attempted', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $id,
        ]);

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $reservation = Reservation::with('room', 'user')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->findOrFail($id);

        $oldStatus = $reservation->status;

        if ($reservation->status !== 'checked_in') {
            Log::warning('Receptionist attempted check-out for non-checked-in reservation', [
                'receptionist_id' => $receptionist->id,
                'reservation_id' => $id,
                'current_status' => $reservation->status,
            ]);
            return response()->json([
                'message' => 'Only checked-in reservations can be checked out'
            ], 422);
        }

        $reservation->status = 'checked_out';
        $reservation->save();

        // Update room status back to available
        $room = $reservation->room;
        if ($room) {
            $oldRoomStatus = $room->is_available;
            
            // Check if room has other active reservations
            $hasOtherActiveReservations = Reservation::where('room_id', $room->id)
                ->where('id', '!=', $reservation->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->where('check_out', '>=', now())
                ->exists();

            $room->is_available = !$hasOtherActiveReservations;
            $room->save();

            Log::info('Room status updated during check-out', [
                'receptionist_id' => $receptionist->id,
                'hotel_id' => $hotelId,
                'room_id' => $room->id,
                'old_room_status' => $oldRoomStatus ? 'available' : 'unavailable',
                'new_room_status' => $room->is_available ? 'available' : 'unavailable',
                'has_other_active_reservations' => $hasOtherActiveReservations,
            ]);
        }

        Log::info('Receptionist check-out completed successfully', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'reservation_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => 'checked_out',
            'room_id' => $reservation->room_id,
            'guest_user_id' => $reservation->user_id,
        ]);

        $reservation->load(['room', 'user']);

        return response()->json([
            'message' => 'Guest checked out successfully',
            'data' => $reservation
        ]);
    }
}

