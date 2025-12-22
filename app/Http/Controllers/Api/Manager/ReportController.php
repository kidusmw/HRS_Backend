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

        // Get reservations that overlap with the date range
        // A reservation overlaps if: check_in <= end AND check_out >= start
        $reservations = Reservation::with('room')
            ->whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            })
            ->where('check_in', '<=', $end->endOfDay())
            ->where('check_out', '>=', $start->startOfDay())
            ->get();

        // Active statuses for revenue/occupancy: confirmed and checked_in
        // Note: checked_out reservations should only count up to their checkout date
        $activeStatuses = ['confirmed', 'checked_in'];
        $revenue = 0;
        $occupiedNights = 0;
        $cancellations = 0;
        $totalBookings = 0;
        $revenueByRoomType = [];
        $bookingsBySource = [];

        foreach ($reservations as $res) {
            $roomPrice = $res->room->price ?? 0;
            $roomType = $res->room->type ?? 'Unknown';
            // Determine source: walk-in bookings use 'walk-in', others default to 'web'
            $source = $res->is_walk_in ? 'walk-in' : ($res->source ?? 'web');
            
            // Calculate overlap between reservation dates and report date range
            $resCheckIn = Carbon::parse($res->check_in)->startOfDay();
            $resCheckOut = Carbon::parse($res->check_out)->startOfDay();
            
            // Overlap period: from max(start, check_in) to min(end, check_out)
            $overlapStart = $resCheckIn->max($start->copy()->startOfDay());
            $overlapEnd = $resCheckOut->min($end->copy()->startOfDay());
            
            // Calculate nights: difference in days
            // If same day (check-in = check-out), that's 1 night
            // Otherwise, diffInDays gives the number of nights
            $nightsDiff = $overlapStart->diffInDays($overlapEnd);
            $nights = $overlapStart->eq($overlapEnd) ? 1 : max(1, $nightsDiff);
            
            // For checked_out reservations, only count nights up to checkout date
            // For active reservations (confirmed/checked_in), count all overlap nights
            if ($res->status === 'checked_out') {
                // Only count if checkout date is within or after the report start
                if ($resCheckOut->gte($start->startOfDay())) {
                    // Recalculate nights for checked_out: only count up to checkout date
                    $checkedOutOverlapStart = $resCheckIn->max($start->copy()->startOfDay());
                    $checkedOutOverlapEnd = $resCheckOut->min($end->copy()->startOfDay());
                    $checkedOutNightsDiff = $checkedOutOverlapStart->diffInDays($checkedOutOverlapEnd);
                    $checkedOutNights = $checkedOutOverlapStart->eq($checkedOutOverlapEnd) ? 1 : max(1, $checkedOutNightsDiff);
                    
                    $occupiedNights += $checkedOutNights;
                    $revenue += $roomPrice * $checkedOutNights;
                    
                    // Track revenue by room type
                    if (!isset($revenueByRoomType[$roomType])) {
                        $revenueByRoomType[$roomType] = 0;
                    }
                    $revenueByRoomType[$roomType] += $roomPrice * $checkedOutNights;
                }
            } elseif (in_array($res->status, $activeStatuses, true)) {
                // For confirmed/checked_in reservations, count all overlap nights
                $occupiedNights += $nights;
                $revenue += $roomPrice * $nights;
                
                // Track revenue by room type
                if (!isset($revenueByRoomType[$roomType])) {
                    $revenueByRoomType[$roomType] = 0;
                }
                $revenueByRoomType[$roomType] += $roomPrice * $nights;
            }

            $totalBookings++;

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
        // For a date range, we calculate the average occupied rooms per day
        // This gives a more accurate representation than a single day snapshot
        $avgOccupiedRoomsPerDay = $days > 0 ? ($occupiedNights / $days) : 0;
        $roomsOccupied = min($roomsCount, (int) round($avgOccupiedRoomsPerDay));
        
        // Calculate available rooms: total rooms minus occupied, but also account for room status
        // Rooms in maintenance/unavailable should not be counted as available
        $roomsInMaintenance = Room::where('hotel_id', $hotelId)
            ->where('status', \App\Enums\RoomStatus::MAINTENANCE)
            ->count();
        $roomsUnavailable = Room::where('hotel_id', $hotelId)
            ->where('status', \App\Enums\RoomStatus::UNAVAILABLE)
            ->count();
        
        // Available rooms = total - occupied - maintenance - unavailable
        $roomsAvailable = max(0, $roomsCount - $roomsOccupied - $roomsInMaintenance - $roomsUnavailable);

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

