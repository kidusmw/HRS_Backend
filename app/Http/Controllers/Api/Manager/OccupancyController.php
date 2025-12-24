<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Enums\RoomStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OccupancyController extends Controller
{
    public function show(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        // Handle case where manager doesn't have a hotel_id assigned
        if ($hotelId === null) {
            return response()->json([
                'data' => [
                    [
                        'label' => 'Today',
                        'occupancyRate' => 0,
                        'roomsOccupied' => 0,
                        'roomsAvailable' => 0,
                    ],
                ],
            ]);
        }

        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();
        $roomsCount = Room::where('hotel_id', $hotelId)->count();

        // Calculate occupancy for today based on room status to match receptionist pages
        $todayOccupied = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::OCCUPIED)
            ->count();
        $todayAvailable = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::AVAILABLE)
            ->count();
        $todayRate = $roomsCount > 0 ? round(($todayOccupied / $roomsCount) * 100, 2) : 0;

        // Calculate occupancy for tomorrow based on reservations (prediction)
        // Note: Tomorrow is a prediction, so we use reservation-based calculation
        $activeStatuses = ['confirmed', 'checked_in', 'checked_out'];
        $tomorrowReservations = Reservation::whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereIn('status', $activeStatuses)
            ->whereDate('check_in', '<=', $tomorrow)
            ->whereDate('check_out', '>=', $tomorrow)
            ->get();
        $tomorrowOccupied = $tomorrowReservations->pluck('room_id')->unique()->count();
        $tomorrowAvailable = max(0, $roomsCount - $tomorrowOccupied);
        $tomorrowRate = $roomsCount > 0 ? round(($tomorrowOccupied / $roomsCount) * 100, 2) : 0;

        // Calculate occupancy for this week (average based on reservations)
        // Note: This week is a prediction/average, so we use reservation-based calculation
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();
        $weekDays = $weekStart->diffInDays($weekEnd) + 1;

        $weekReservations = Reservation::whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereIn('status', $activeStatuses)
            ->whereDate('check_in', '<=', $weekEnd)
            ->whereDate('check_out', '>=', $weekStart)
            ->get();

        $weekOccupiedNights = 0;
        foreach ($weekReservations as $res) {
            $overlapStart = Carbon::parse($res->check_in)->max($weekStart);
            $overlapEnd = Carbon::parse($res->check_out)->min($weekEnd);
            $nights = max(0, $overlapStart->diffInDays($overlapEnd));
            $weekOccupiedNights += $nights;
        }

        $weekAvailableNights = $roomsCount * $weekDays;
        $weekRate = $weekAvailableNights > 0 ? round(($weekOccupiedNights / $weekAvailableNights) * 100, 2) : 0;
        $weekAvgOccupied = $weekDays > 0 ? (int) round($weekOccupiedNights / $weekDays) : 0;
        $weekAvgAvailable = max(0, $roomsCount - $weekAvgOccupied);

        return response()->json([
            'data' => [
                [
                    'label' => 'Today',
                    'occupancyRate' => $todayRate,
                    'roomsOccupied' => $todayOccupied,
                    'roomsAvailable' => $todayAvailable,
                ],
                [
                    'label' => 'Tomorrow',
                    'occupancyRate' => $tomorrowRate,
                    'roomsOccupied' => $tomorrowOccupied,
                    'roomsAvailable' => $tomorrowAvailable,
                ],
                [
                    'label' => 'This Week',
                    'occupancyRate' => $weekRate,
                    'roomsOccupied' => $weekAvgOccupied,
                    'roomsAvailable' => $weekAvgAvailable,
                ],
            ],
        ]);
    }
}

