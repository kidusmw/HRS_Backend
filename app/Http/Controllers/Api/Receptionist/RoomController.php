<?php

namespace App\Http\Controllers\Api\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Room;
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

        // Filter by availability status
        if (!is_null($request->input('isAvailable'))) {
            $query->where('is_available', filter_var($request->input('isAvailable'), FILTER_VALIDATE_BOOLEAN));
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

        $oldStatus = $room->is_available ? 'available' : 'unavailable';
        
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
        
        // Map status to is_available field
        // Note: The Room model uses is_available boolean
        // - available -> is_available = true
        // - occupied/maintenance/unavailable -> is_available = false
        // The transformRoom method will check for active reservations to determine if it's "occupied"
        // vs "unavailable" or "maintenance"
        $isAvailable = ($newStatus === 'available');
        
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
        
        $room->is_available = $isAvailable;
        $room->save();

        Log::info('Receptionist room status updated successfully', [
            'receptionist_id' => $user->id,
            'hotel_id' => $hotelId,
            'room_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'is_available' => $isAvailable,
        ]);

        return response()->json([
            'message' => 'Room status updated successfully',
            'data' => $this->transformRoom($room->fresh())
        ]);
    }

    /**
     * Transform room model to frontend format
     * @param Room $room
     * @return array
     */
    private function transformRoom(Room $room): array
    {
        // Determine status based on is_available and any active reservations
        $status = 'available';
        if (!$room->is_available) {
            // Check if room has active checked-in reservation
            $hasActiveReservation = $room->reservations()
                ->where('status', 'checked_in')
                ->where('check_out', '>=', now())
                ->exists();
            
            $status = $hasActiveReservation ? 'occupied' : 'unavailable';
        }

        return [
            'id' => $room->id,
            'number' => (string) $room->id, // Using ID as number for now, adjust if you have a number field
            'type' => $room->type,
            'price' => (float) $room->price,
            'capacity' => $room->capacity,
            'description' => $room->description,
            'status' => $status,
            'isAvailable' => $room->is_available,
            'createdAt' => $room->created_at?->toIso8601String(),
            'updatedAt' => $room->updated_at?->toIso8601String(),
        ];
    }
}

