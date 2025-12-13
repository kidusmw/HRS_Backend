<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        // Handle case where manager doesn't have a hotel_id assigned
        if ($hotelId === null) {
            return response()->json([
                'data' => [
                    'occupancy' => [
                        'rate' => 0,
                        'roomsOccupied' => 0,
                        'roomsAvailable' => 0,
                    ],
                    'revenue' => [
                        'total' => 0,
                        'byRoomType' => [],
                    ],
                    'bookings' => [
                        'total' => 0,
                        'bySource' => [],
                        'cancellations' => 0,
                    ],
                    'metrics' => [
                        'adr' => 0,
                        'revpar' => 0,
                    ],
                ],
            ]);
        }

        $range = $request->input('range', 'last_30_days');
        [$start, $end] = $this->resolveRange($range);

        $days = max(1, $start->diffInDays($end) + 1);
        $roomsCount = Room::where('hotel_id', $hotelId)->count();

        $reservations = Reservation::with('room')
            ->whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            })
            ->whereDate('check_in', '<=', $end)
            ->whereDate('check_out', '>=', $start)
            ->get();

        $activeStatuses = ['confirmed', 'checked_in', 'checked_out'];
        $revenue = 0;
        $occupiedNights = 0;
        $cancellations = 0;
        $totalBookings = 0;
        $revenueByRoomType = [];
        $bookingsBySource = [];

        foreach ($reservations as $res) {
            $roomPrice = $res->room->price ?? 0;
            $roomType = $res->room->type ?? 'Unknown';
            $source = $res->source ?? 'web';
            $overlapStart = Carbon::parse($res->check_in)->max($start);
            $overlapEnd = Carbon::parse($res->check_out)->min($end);
            $nights = max(0, $overlapStart->diffInDays($overlapEnd));

            $totalBookings++;

            if (in_array($res->status, $activeStatuses, true)) {
                $occupiedNights += $nights;
                $revenue += $roomPrice * $nights;
                
                // Track revenue by room type
                if (!isset($revenueByRoomType[$roomType])) {
                    $revenueByRoomType[$roomType] = 0;
                }
                $revenueByRoomType[$roomType] += $roomPrice * $nights;
            }

            if ($res->status === 'cancelled') {
                $cancellations++;
            }

            // Track bookings by source
            if (!isset($bookingsBySource[$source])) {
                $bookingsBySource[$source] = 0;
            }
            $bookingsBySource[$source]++;
        }

        $availableRoomNights = $roomsCount * $days;
        $occupancyRate = $availableRoomNights > 0
            ? round(($occupiedNights / $availableRoomNights) * 100, 2)
            : 0;

        $adr = $occupiedNights > 0 ? round($revenue / $occupiedNights, 2) : 0;
        $revpar = $availableRoomNights > 0 ? round($revenue / $availableRoomNights, 2) : 0;

        // Calculate rooms occupied/available for the date range
        // This is an approximation: average occupied rooms per day
        $roomsOccupied = min($roomsCount, (int) ceil($occupiedNights / $days));
        $roomsAvailable = max(0, $roomsCount - $roomsOccupied);

        // Format revenue by room type
        $revenueByRoomTypeFormatted = array_map(function ($type, $rev) {
            return ['type' => $type, 'revenue' => round($rev, 2)];
        }, array_keys($revenueByRoomType), $revenueByRoomType);

        // Format bookings by source
        $bookingsBySourceFormatted = array_map(function ($source, $count) {
            return ['source' => $source, 'count' => $count];
        }, array_keys($bookingsBySource), $bookingsBySource);

        return response()->json([
            'data' => [
                'occupancy' => [
                    'rate' => $occupancyRate,
                    'roomsOccupied' => $roomsOccupied,
                    'roomsAvailable' => $roomsAvailable,
                ],
                'revenue' => [
                    'total' => round($revenue, 2),
                    'byRoomType' => $revenueByRoomTypeFormatted,
                ],
                'bookings' => [
                    'total' => $totalBookings,
                    'bySource' => $bookingsBySourceFormatted,
                    'cancellations' => $cancellations,
                ],
                'metrics' => [
                    'adr' => $adr,
                    'revpar' => $revpar,
                ],
            ],
        ]);
    }

    private function resolveRange(string $range): array
    {
        $today = Carbon::today();
        return match ($range) {
            'today' => [$today, $today],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'last_7_days', 'last_7' => [$today->copy()->subDays(6), $today],
            'last_30_days', 'last_30' => [$today->copy()->subDays(29), $today],
            'six_months' => [$today->copy()->subMonthsNoOverflow(6), $today],
            'yearly', 'year' => [$today->copy()->subYear(), $today],
            default => [$today->copy()->subDays(29), $today],
        };
    }
}

