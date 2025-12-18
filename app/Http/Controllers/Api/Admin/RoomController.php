<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Reservation;

class RoomController extends Controller
{
    /**
     * Get all rooms for the admin's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns a paginated list of rooms for the hotel.
     * Supports filtering by search, type, and availability status.
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

        $query = Room::where('hotel_id', $hotelId);

        // Search by type or description
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
        
        // Also support legacy isAvailable filter for backward compatibility (maps to status)
        if (!is_null($request->input('isAvailable'))) {
            $isAvailable = filter_var($request->input('isAvailable'), FILTER_VALIDATE_BOOLEAN);
            if ($isAvailable) {
                $query->where('status', \App\Enums\RoomStatus::AVAILABLE);
            } else {
                $query->where('status', '!=', \App\Enums\RoomStatus::AVAILABLE);
            }
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
     * Get a specific room
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

        $room = Room::where('id', $id)
            ->where('hotel_id', $hotelId)
            ->firstOrFail();

        return response()->json([
            'data' => $this->transformRoom($room)
        ]);
    }

    /**
     * Create a new room
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Validate input
        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'isAvailable' => 'sometimes|boolean', // Optional - availability managed by receptionists/managers
            'capacity' => 'required|integer|min:1|max:100', // Number of rooms of this type to create
            'description' => 'nullable|string',
        ]);

        $numberOfRooms = $validated['capacity'];
        $createdRooms = [];

        // Create multiple rooms - each room gets its own auto-incrementing ID
        // Each room will have the same type, price, and description
        // Guest capacity defaults to 1 (can be updated later if needed)
        for ($i = 0; $i < $numberOfRooms; $i++) {
            $room = Room::create([
                'hotel_id' => $hotelId,
                'type' => $validated['type'],
                'price' => $validated['price'],
                // status defaults to available for new rooms
                // Availability is managed by receptionists/managers, not admins
                'status' => \App\Enums\RoomStatus::AVAILABLE,
                'capacity' => 1, // Guest capacity per room (default to 1, can be updated later if needed)
                'description' => $validated['description'] ?? null,
            ]);
            $createdRooms[] = $this->transformRoom($room);
        }

        return response()->json([
            'message' => "Successfully created {$numberOfRooms} room(s)",
            'data' => $createdRooms,
            'count' => count($createdRooms),
        ], 201);
    }

    /**
     * Update a room
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $room = Room::where('id', $id)
            ->where('hotel_id', $hotelId)
            ->firstOrFail();

        // Validate input
        $validated = $request->validate([
            'type' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string|in:available,unavailable,occupied,maintenance', // Optional - status managed by receptionists/managers
            'capacity' => 'sometimes|integer|min:1', // Number of rooms of this type
            'description' => 'nullable|string',
        ]);

        // Update fields
        // Note: status is not typically updated by admins - it's managed by receptionists/managers
        if (isset($validated['type'])) {
            $room->type = $validated['type'];
        }
        if (isset($validated['price'])) {
            $room->price = $validated['price'];
        }
        if (isset($validated['status'])) {
            $room->status = \App\Enums\RoomStatus::from($validated['status']);
        }
        if (isset($validated['capacity'])) {
            $room->capacity = $validated['capacity']; // Number of rooms of this type
        }
        if (isset($validated['description'])) {
            $room->description = $validated['description'];
        }

        $room->save();

        return response()->json([
            'data' => $this->transformRoom($room)
        ]);
    }

    /**
     * Delete a room
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $room = Room::where('id', $id)
            ->where('hotel_id', $hotelId)
            ->firstOrFail();

        // Check if room has active reservations
        $hasActiveReservations = Reservation::where('room_id', $id)
            ->where('status', 'confirmed')
            ->where('check_out', '>=', now())
            ->exists();

        if ($hasActiveReservations) {
            return response()->json([
                'message' => 'Cannot delete room with active reservations'
            ], 422);
        }

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully'
        ], 200);
    }

    /**
     * Transform room model to frontend format
     * @param Room $room
     * @return array
     */
    private function transformRoom(Room $room): array
    {
        return [
            'id' => $room->id,
            'hotelId' => $room->hotel_id,
            'type' => $room->type,
            'price' => (float) $room->price,
            'status' => $room->status?->value ?? 'available',
            'isAvailable' => $room->is_available, // Computed accessor from status
            'capacity' => $room->capacity,
            'description' => $room->description,
            'createdAt' => $room->created_at?->toIso8601String(),
            'updatedAt' => $room->updated_at?->toIso8601String(),
        ];
    }
}

