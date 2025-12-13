<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OccupancyController extends Controller
{
    public function show(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;
        $today = Carbon::today();

        $roomsCount = Room::where('hotel_id', $hotelId)->count();

        $activeStatuses = ['confirmed', 'checked_in', 'checked_out'];
        $occupied = Reservation::whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereIn('status', $activeStatuses)
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->count();

        $available = max(0, $roomsCount - $occupied);
        $occupancyRate = $roomsCount > 0 ? round(($occupied / $roomsCount) * 100, 2) : 0;

        return response()->json([
            'roomsTotal' => $roomsCount,
            'roomsOccupied' => $occupied,
            'roomsAvailable' => $available,
            'occupancyRate' => $occupancyRate,
            'date' => $today->toDateString(),
        ]);
    }
}

