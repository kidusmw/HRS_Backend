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
            'capacity' => 'required|integer|min:1', // Number of rooms of this type
            'description' => 'nullable|string',
        ]);

        $room = Room::create([
            'hotel_id' => $hotelId,
            'type' => $validated['type'],
            'price' => $validated['price'],
            // is_available defaults to true for new rooms
            // Availability is managed by receptionists/managers, not admins
            'is_available' => $validated['isAvailable'] ?? true,
            'capacity' => $validated['capacity'], // Number of rooms of this type
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'data' => $this->transformRoom($room)
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
            'isAvailable' => 'sometimes|boolean', // Optional - availability managed by receptionists/managers
            'capacity' => 'sometimes|integer|min:1', // Number of rooms of this type
            'description' => 'nullable|string',
        ]);

        // Update fields
        // Note: isAvailable is not updated by admins - it's managed by receptionists/managers
        if (isset($validated['type'])) {
            $room->type = $validated['type'];
        }
        if (isset($validated['price'])) {
            $room->price = $validated['price'];
        }
        if (isset($validated['isAvailable'])) {
            // This field is kept for future use by receptionists/managers
            // Admins don't send this field, but we keep the logic for consistency
            $room->is_available = $validated['isAvailable'];
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
            'isAvailable' => $room->is_available,
            'capacity' => $room->capacity,
            'description' => $room->description,
            'createdAt' => $room->created_at?->toIso8601String(),
            'updatedAt' => $room->updated_at?->toIso8601String(),
        ];
    }
}

