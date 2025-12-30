<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Reservation;
use App\Enums\RoomStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvailabilityController extends Controller
{
    /**
     * Get availability by room type for a hotel and date range (public, no auth required)
     */
    public function show(Request $request, int $hotelId)
    {
        $hotel = Hotel::findOrFail($hotelId);

        $startDate = $request->input('start');
        $endDate = $request->input('end');

        if (!$startDate || !$endDate) {
            return response()->json([
                'message' => 'start and end date parameters are required (YYYY-MM-DD format)'
            ], 400);
        }

        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid date format. Use YYYY-MM-DD'
            ], 400);
        }

        if ($start->gt($end)) {
            return response()->json([
                'message' => 'Start date must be before or equal to end date'
            ], 400);
        }

        // Get all rooms for this hotel, grouped by type
        // We'll filter by availability status when counting
        $rooms = Room::where('hotel_id', $hotelId)
            ->get()
            ->groupBy('type');

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

        // Build availability by room type for the entire date range
        $availabilityByType = [];

        foreach ($rooms as $type => $typeRooms) {
            $availableCount = 0;
            $minPrice = null;

            foreach ($typeRooms as $room) {
                // Only consider rooms that are available (not in maintenance, unavailable, or occupied)
                if ($room->status !== RoomStatus::AVAILABLE) {
                    continue;
                }

                // Check if this room is blocked for the entire date range
                $isBlocked = false;
                
                if (isset($reservations[$room->id])) {
                    foreach ($reservations[$room->id] as $reservation) {
                        $checkIn = Carbon::parse($reservation->check_in);
                        $checkOut = Carbon::parse($reservation->check_out);
                        
                        // Check if reservation overlaps with the date range (inclusive)
                        // A room is blocked if any day in the range overlaps with the reservation
                        if (!($checkOut->lt($start) || $checkIn->gt($end))) {
                            $isBlocked = true;
                            break;
                        }
                    }
                }

                if (!$isBlocked) {
                    $availableCount++;
                    if ($minPrice === null || $room->price < $minPrice) {
                        $minPrice = (float) $room->price;
                    }
                }
            }

            $availabilityByType[] = [
                'type' => $type,
                'totalRooms' => $typeRooms->count(),
                'availableRooms' => $availableCount,
                'priceFrom' => $availableCount > 0 ? $minPrice : null,
            ];
        }

        return response()->json([
            'data' => $availabilityByType,
        ]);
    }
}
