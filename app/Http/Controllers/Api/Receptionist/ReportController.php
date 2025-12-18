<?php

namespace App\Http\Controllers\Api\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Enums\RoomStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Get operational reports for receptionist
     * Returns arrivals, departures, in-house guests, and occupancy data
     */
    public function index(Request $request)
    {
        $receptionist = $request->user();
        $hotelId = $receptionist->hotel_id;

        Log::info('Receptionist report accessed', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
        ]);

        // Handle case where receptionist doesn't have a hotel_id assigned
        if ($hotelId === null) {
            Log::warning('Receptionist report accessed without hotel_id', [
                'receptionist_id' => $receptionist->id,
                'receptionist_email' => $receptionist->email,
            ]);
            return response()->json([
                'arrivals' => ['total' => 0, 'list' => []],
                'departures' => ['total' => 0, 'list' => []],
                'inHouse' => ['total' => 0, 'list' => []],
                'occupancy' => [
                    'rate' => 0,
                    'totalRooms' => 0,
                    'occupiedRooms' => 0,
                    'availableRooms' => 0,
                ],
                'dateRange' => [
                    'start' => Carbon::today()->toDateString(),
                    'end' => Carbon::today()->toDateString(),
                ],
            ]);
        }

        $range = $request->input('range', 'today');
        [$start, $end] = $this->resolveRange($range);

        $startDateStr = $start->toDateString();
        $endDateStr = $end->toDateString();

        // Get arrivals (check-ins during the period)
        $arrivals = Reservation::with(['room', 'user'])
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereBetween('check_in', [$startDateStr, $endDateStr])
            ->whereIn('status', ['pending', 'confirmed', 'checked_in', 'checked_out'])
            ->orderBy('check_in')
            ->get();

        // Get departures (check-outs during the period)
        $departures = Reservation::with(['room', 'user'])
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereBetween('check_out', [$startDateStr, $endDateStr])
            ->where('status', 'checked_out')
            ->orderBy('check_out')
            ->get();

        // Get in-house guests (currently checked in)
        $inHouse = Reservation::with(['room', 'user'])
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('status', 'checked_in')
            ->orderBy('check_in')
            ->get();

        // Calculate occupancy metrics
        $totalRooms = Room::where('hotel_id', $hotelId)->count();
        $occupiedRooms = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::OCCUPIED)
            ->count();
        $availableRooms = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::AVAILABLE)
            ->count();
        $occupancyRate = $totalRooms > 0 
            ? round(($occupiedRooms / $totalRooms) * 100, 1) 
            : 0;

        // Transform arrivals
        $arrivalsList = $arrivals->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                'guestName' => $reservation->user->name ?? 'Guest',
                'roomNumber' => (string) ($reservation->room->id ?? 'N/A'),
                'checkIn' => $reservation->check_in->toDateString(),
                'status' => $reservation->status,
            ];
        })->toArray();

        // Transform departures
        $departuresList = $departures->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                'guestName' => $reservation->user->name ?? 'Guest',
                'roomNumber' => (string) ($reservation->room->id ?? 'N/A'),
                'checkOut' => $reservation->check_out->toDateString(),
            ];
        })->toArray();

        // Transform in-house
        $inHouseList = $inHouse->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                'guestName' => $reservation->user->name ?? 'Guest',
                'roomNumber' => (string) ($reservation->room->id ?? 'N/A'),
                'checkIn' => $reservation->check_in->toDateString(),
                'checkOut' => $reservation->check_out->toDateString(),
            ];
        })->toArray();

        Log::info('Receptionist report generated', [
            'receptionist_id' => $receptionist->id,
            'hotel_id' => $hotelId,
            'range' => $range,
            'arrivals_count' => count($arrivalsList),
            'departures_count' => count($departuresList),
            'in_house_count' => count($inHouseList),
        ]);

        return response()->json([
            'arrivals' => [
                'total' => count($arrivalsList),
                'list' => $arrivalsList,
            ],
            'departures' => [
                'total' => count($departuresList),
                'list' => $departuresList,
            ],
            'inHouse' => [
                'total' => count($inHouseList),
                'list' => $inHouseList,
            ],
            'occupancy' => [
                'rate' => $occupancyRate,
                'totalRooms' => $totalRooms,
                'occupiedRooms' => $occupiedRooms,
                'availableRooms' => $availableRooms,
            ],
            'dateRange' => [
                'start' => $startDateStr,
                'end' => $endDateStr,
            ],
        ]);
    }

    /**
     * Resolve date range from string
     */
    private function resolveRange(string $range): array
    {
        $today = Carbon::today();
        return match ($range) {
            'today' => [$today, $today],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'last_7_days' => [$today->copy()->subDays(6), $today],
            'last_30_days' => [$today->copy()->subDays(29), $today],
            default => [$today, $today],
        };
    }
}

