<?php

namespace App\Http\Controllers\Api\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Enums\RoomStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $receptionist = $request->user();
        $hotelId = $receptionist->hotel_id;

        Log::info('Receptionist dashboard accessed', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
        ]);

        // Handle case where receptionist doesn't have a hotel_id assigned
        if ($hotelId === null) {
            Log::warning('Receptionist dashboard accessed without hotel_id', [
                'receptionist_id' => $receptionist->id,
                'receptionist_email' => $receptionist->email,
            ]);
            return response()->json([
                'arrivals' => 0,
                'departures' => 0,
                'inHouse' => 0,
                'occupancy' => [
                    'rate' => 0,
                    'totalRooms' => 0,
                    'occupiedRooms' => 0,
                    'availableRooms' => 0,
                ],
            ]);
        }

        $today = Carbon::today();

        // Today's arrivals (pending or confirmed reservations checking in today)
        // Includes both regular bookings and walk-in bookings
        $todayArrivals = Reservation::with('room', 'user')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereDate('check_in', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        // Today's departures (checked-in guests checking out today)
        // Includes both regular bookings and walk-in bookings
        $todayDepartures = Reservation::with('room', 'user')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereDate('check_out', $today)
            ->where('status', 'checked_in')
            ->count();

        // Currently in-house (checked-in guests)
        // Includes both regular bookings and walk-in bookings
        $inHouse = Reservation::with('room', 'user')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('status', 'checked_in')
            ->count();

        // Occupancy metrics
        // Calculate based on room status to match Reports page
        $totalRooms = Room::where('hotel_id', $hotelId)->count();
        $occupiedRooms = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::OCCUPIED)
            ->count();
        $availableRooms = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::AVAILABLE)
            ->count();
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        Log::info('Receptionist dashboard metrics calculated', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'arrivals' => $todayArrivals,
            'departures' => $todayDepartures,
            'in_house' => $inHouse,
            'occupancy_rate' => $occupancyRate,
        ]);

        return response()->json([
            'arrivals' => $todayArrivals,
            'departures' => $todayDepartures,
            'inHouse' => $inHouse,
            'occupancy' => [
                'rate' => $occupancyRate,
                'totalRooms' => $totalRooms,
                'occupiedRooms' => $occupiedRooms,
                'availableRooms' => $availableRooms,
            ],
        ]);
    }
}

