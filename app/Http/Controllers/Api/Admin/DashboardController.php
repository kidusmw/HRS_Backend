<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Reservation;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard metrics for the admin's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns hotel-specific metrics including:
     * - KPIs (occupancy percentage, available rooms, active reservations, upcoming check-ins)
     * - Monthly revenue
     * - Booking trends (6 months)
     * - Weekly occupancy trends
     * - Revenue trends (6 months)
     */
    public function metrics(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Calculate KPIs
        $totalRooms = Room::where('hotel_id', $hotelId)->count();
        
        // Occupied rooms (confirmed reservations that are currently active)
        $occupiedRooms = Reservation::whereHas('room', function ($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where('status', 'confirmed')
        ->where('check_in', '<=', now())
        ->where('check_out', '>=', now())
        ->distinct('room_id')
        ->count('room_id');
        
        $availableRooms = max(0, $totalRooms - $occupiedRooms);
        $occupancyPct = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        // Active reservations today (confirmed reservations that include today)
        $activeReservationsToday = Reservation::whereHas('room', function ($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where('status', 'confirmed')
        ->where('check_in', '<=', now())
        ->where('check_out', '>=', now())
        ->count();

        // Upcoming check-ins (reservations with check_in date today or in the next 7 days)
        $upcomingCheckins = Reservation::whereHas('room', function ($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where('status', 'confirmed')
        ->whereBetween('check_in', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
        ->count();

        // Monthly revenue (current month)
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        
        $monthlyRevenue = Reservation::whereHas('room', function ($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where('status', 'confirmed')
        ->whereBetween('check_in', [$monthStart, $monthEnd])
        ->join('rooms', 'reservations.room_id', '=', 'rooms.id')
        ->where('rooms.hotel_id', $hotelId)
        ->selectRaw('SUM(rooms.price * DATEDIFF(reservations.check_out, reservations.check_in)) as revenue')
        ->value('revenue') ?? 0;

        // Booking trends for last 6 months
        $bookingTrends = $this->getBookingTrends($hotelId);

        // Weekly occupancy for current month
        $weeklyOccupancy = $this->getWeeklyOccupancy($hotelId);

        // Revenue trends for last 6 months
        $revenueTrends = $this->getRevenueTrends($hotelId);

        return response()->json([
            'kpis' => [
                'occupancyPct' => $occupancyPct,
                'roomsAvailable' => $availableRooms,
                'activeReservationsToday' => $activeReservationsToday,
                'upcomingCheckins' => $upcomingCheckins,
            ],
            'monthlyRevenue' => (float) $monthlyRevenue,
            'bookingTrends' => $bookingTrends,
            'weeklyOccupancy' => $weeklyOccupancy,
            'revenueTrends' => $revenueTrends,
        ]);
    }

    /**
     * Get booking trends for the last 6 months
     */
    private function getBookingTrends(int $hotelId): array
    {
        $months = [];
        $now = now();
        
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            $bookings = Reservation::whereHas('room', function ($query) use ($hotelId) {
                $query->where('hotel_id', $hotelId);
            })
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();
            
            $months[] = [
                'month' => $monthStart->format('M'),
                'bookings' => $bookings,
            ];
        }
        
        return $months;
    }

    /**
     * Get weekly occupancy for current month
     */
    private function getWeeklyOccupancy(int $hotelId): array
    {
        $weeks = [];
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $totalRooms = Room::where('hotel_id', $hotelId)->count();
        
        // Get all weeks in the current month
        $currentWeekStart = $monthStart->copy();
        $weekNumber = 1;
        
        while ($currentWeekStart->lte($monthEnd)) {
            $weekEnd = $currentWeekStart->copy()->addDays(6)->endOfDay();
            if ($weekEnd->gt($monthEnd)) {
                $weekEnd = $monthEnd->copy()->endOfDay();
            }
            
            // Count rooms that have active reservations during this week
            $occupiedRooms = Reservation::whereHas('room', function ($query) use ($hotelId) {
                $query->where('hotel_id', $hotelId);
            })
            ->where('status', 'confirmed')
            ->where(function ($query) use ($currentWeekStart, $weekEnd) {
                $query->where(function ($q) use ($currentWeekStart, $weekEnd) {
                    // Reservation starts in this week
                    $q->whereBetween('check_in', [$currentWeekStart, $weekEnd]);
                })->orWhere(function ($q) use ($currentWeekStart, $weekEnd) {
                    // Reservation ends in this week
                    $q->whereBetween('check_out', [$currentWeekStart, $weekEnd]);
                })->orWhere(function ($q) use ($currentWeekStart, $weekEnd) {
                    // Reservation spans the entire week
                    $q->where('check_in', '<=', $currentWeekStart)
                      ->where('check_out', '>=', $weekEnd);
                });
            })
            ->distinct('room_id')
            ->count('room_id');
            
            $availableRooms = max(0, $totalRooms - $occupiedRooms);
            
            $weeks[] = [
                'week' => "Week {$weekNumber}",
                'occupied' => $occupiedRooms,
                'available' => $availableRooms,
            ];
            
            $currentWeekStart->addWeek();
            $weekNumber++;
            
            // Safety check to prevent infinite loop
            if ($weekNumber > 6) {
                break;
            }
        }
        
        return $weeks;
    }

    /**
     * Get revenue trends for the last 6 months
     */
    private function getRevenueTrends(int $hotelId): array
    {
        $months = [];
        $now = now();
        
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            $revenue = Reservation::whereHas('room', function ($query) use ($hotelId) {
                $query->where('hotel_id', $hotelId);
            })
            ->where('status', 'confirmed')
            ->whereBetween('check_in', [$monthStart, $monthEnd])
            ->join('rooms', 'reservations.room_id', '=', 'rooms.id')
            ->where('rooms.hotel_id', $hotelId)
            ->selectRaw('SUM(rooms.price * GREATEST(1, DATEDIFF(reservations.check_out, reservations.check_in))) as revenue')
            ->value('revenue') ?? 0;
            
            $months[] = [
                'month' => $monthStart->format('M'),
                'revenue' => (float) $revenue,
            ];
        }
        
        return $months;
    }
}

