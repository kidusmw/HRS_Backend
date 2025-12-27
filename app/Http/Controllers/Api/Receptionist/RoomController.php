<?php

namespace App\Http\Controllers\Api\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Reservation;
use App\Enums\RoomStatus;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RoomController extends Controller
{
    /**
     * Get all rooms for the receptionist's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns a paginated list of rooms for the hotel.
     * Supports filtering by search, type, and status.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        Log::info('Receptionist rooms list accessed', [
            'receptionist_id' => $user->id,
            'hotel_id' => $hotelId,
        ]);

        if (!$hotelId) {
            Log::warning('Receptionist rooms list accessed without hotel_id', [
                'receptionist_id' => $user->id,
            ]);
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $query = Room::where('hotel_id', $hotelId);

        // Search by type, description, or room number (if we have a number field)
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $rooms = $query->orderBy('type')->paginate($request->integer('per_page', 15));

        // Transform to match frontend format
        $transformedRooms = $rooms->getCollection()->map(function ($room) {
            return $this->transformRoom($room);
        });

        return response()->json([
            'data' => $transformedRooms,
            'links' => $rooms->linkCollection(),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'from' => $rooms->firstItem(),
                'last_page' => $rooms->lastPage(),
                'per_page' => $rooms->perPage(),
                'to' => $rooms->lastItem(),
                'total' => $rooms->total(),
            ],
        ]);
    }

    /**
     * Update room status only
     * Receptionists can only update status, not other room details
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        Log::info('Receptionist room status update attempted', [
            'receptionist_id' => $user->id,
            'hotel_id' => $hotelId,
            'room_id' => $id,
        ]);

        if (!$hotelId) {
            Log::warning('Receptionist room status update without hotel_id', [
                'receptionist_id' => $user->id,
                'room_id' => $id,
            ]);
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $room = Room::where('id', $id)
            ->where('hotel_id', $hotelId)
            ->first();

        if (!$room) {
            Log::warning('Receptionist attempted to update non-existent room', [
                'receptionist_id' => $user->id,
                'hotel_id' => $hotelId,
                'room_id' => $id,
            ]);
            return response()->json([
                'message' => 'Room not found'
            ], 404);
        }

        $oldStatus = $room->status?->value ?? 'available';
        
        // Validate status update
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:available,occupied,maintenance,unavailable',
        ]);

        if ($validator->fails()) {
            Log::warning('Receptionist room status update validation failed', [
                'receptionist_id' => $user->id,
                'room_id' => $id,
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $newStatus = $request->input('status');
        
        // Validate status value
        $validStatuses = ['available', 'unavailable', 'occupied', 'maintenance'];
        if (!in_array($newStatus, $validStatuses)) {
            return response()->json([
                'message' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)
            ], 422);
        }
        
        // If setting to occupied, check if there's actually an active reservation
        if ($newStatus === 'occupied') {
            $hasActiveReservation = \App\Models\Reservation::where('room_id', $room->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->where('check_out', '>=', now())
                ->exists();
            
            if (!$hasActiveReservation) {
                Log::warning('Receptionist attempted to set room to occupied without active reservation', [
                    'receptionist_id' => $user->id,
                    'room_id' => $id,
                ]);
                return response()->json([
                    'message' => 'Cannot set room to occupied without an active reservation'
                ], 422);
            }
        }
        
        // Update status field
        $room->status = \App\Enums\RoomStatus::from($newStatus);
        $room->save();

        Log::info('Receptionist room status updated successfully', [
            'receptionist_id' => $user->id,
            'hotel_id' => $hotelId,
            'room_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        // Create audit log for room status update
        AuditLogger::log('room.status.updated', $user, $hotelId, [
            'room_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return response()->json([
            'message' => 'Room status updated successfully',
            'data' => $this->transformRoom($room->fresh())
        ]);
    }

    /**
     * Get available rooms for a date range
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function available(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        Log::info('Receptionist available rooms list accessed', [
            'receptionist_id' => $user->id,
            'hotel_id' => $hotelId,
        ]);

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $checkIn = $request->input('check_in');
        $checkOut = $request->input('check_out');
        $roomType = $request->input('room_type');

        if (!$checkIn || !$checkOut) {
            return response()->json([
                'message' => 'check_in and check_out parameters are required (YYYY-MM-DD format)'
            ], 400);
        }

        try {
            $start = Carbon::parse($checkIn);
            $end = Carbon::parse($checkOut);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid date format. Use YYYY-MM-DD'
            ], 400);
        }

        if ($start->gt($end)) {
            return response()->json([
                'message' => 'Check-in date must be before or equal to check-out date'
            ], 400);
        }

        // Get all rooms for this hotel with status = available
        $query = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::AVAILABLE);

        // Filter by room type if provided
        if ($roomType) {
            $query->where('type', $roomType);
        }

        $rooms = $query->get();

        // Get all reservations that might overlap with the date range
        $reservations = Reservation::whereHas('room', function ($q) use ($hotelId) {
            $q->where('hotel_id', $hotelId);
        })
        ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
        ->where(function ($q) use ($start, $end) {
            // Reservations that overlap with the date range (inclusive overlap)
            $q->where(function ($sub) use ($start, $end) {
                // Reservation starts within range
                $sub->whereBetween('check_in', [$start->toDateString(), $end->toDateString()]);
            })
            ->orWhere(function ($sub) use ($start, $end) {
                // Reservation ends within range
                $sub->whereBetween('check_out', [$start->toDateString(), $end->toDateString()]);
            })
            ->orWhere(function ($sub) use ($start, $end) {
                // Reservation completely contains the range
                $sub->where('check_in', '<=', $start->toDateString())
                    ->where('check_out', '>=', $end->toDateString());
            });
        })
        ->get()
        ->groupBy('room_id');

        // Filter out rooms that are blocked by reservations
        $availableRooms = $rooms->filter(function ($room) use ($reservations, $start, $end) {
            if (!isset($reservations[$room->id])) {
                return true; // No reservations for this room
            }

            // Check if any reservation overlaps with the date range
            foreach ($reservations[$room->id] as $reservation) {
                $resCheckIn = Carbon::parse($reservation->check_in);
                $resCheckOut = Carbon::parse($reservation->check_out);
                
                // Check if reservation overlaps (inclusive)
                if (!($resCheckOut->lt($start) || $resCheckIn->gt($end))) {
                    return false; // Room is blocked
                }
            }

            return true; // Room is available
        });

        // Transform to frontend format
        $transformedRooms = $availableRooms->map(function ($room) {
            return $this->transformRoom($room);
        })->values();

        return response()->json([
            'data' => $transformedRooms,
        ]);
    }

    /**
     * Transform room model to frontend format
     * @param Room $room
     * @return array
     */
    private function transformRoom(Room $room): array
    {
        // Status is now the source of truth
        $status = $room->status?->value ?? 'available';

        return [
            'id' => $room->id,
            'number' => (string) $room->id, // Using ID as number for now, adjust if you have a number field
            'type' => $room->type,
            'price' => (float) $room->price,
            'capacity' => $room->capacity,
            'description' => $room->description,
            'status' => $status,
            'isAvailable' => $room->is_available, // Computed accessor from status
            'createdAt' => $room->created_at?->toIso8601String(),
            'updatedAt' => $room->updated_at?->toIso8601String(),
        ];
    }
}

