<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Reservation;
use App\Models\User;
use App\Enums\UserRole;

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
        
        // Occupied rooms: rooms with checked_in status OR confirmed reservations that are currently active
        // This matches the receptionist dashboard logic
        $occupiedRooms = Reservation::whereHas('room', function ($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where(function ($query) {
            // Currently checked-in guests
            $query->where('status', 'checked_in')
                  // OR confirmed reservations that are currently active (check-in <= today <= check-out)
                  ->orWhere(function ($q) {
                      $q->where('status', 'confirmed')
                        ->where('check_in', '<=', now())
                        ->where('check_out', '>=', now());
                  });
        })
        ->distinct('room_id')
        ->count('room_id');
        
        // Available rooms: rooms with status = 'available' (using room status enum)
        // This is more accurate than subtracting, as it accounts for maintenance/unavailable rooms
        $availableRooms = Room::where('hotel_id', $hotelId)
            ->where('status', \App\Enums\RoomStatus::AVAILABLE)
            ->count();
        
        $occupancyPct = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        // Calculate last month's occupancy for comparison (same day last month)
        // This provides a fair comparison - what was occupancy on this exact day last month?
        $sameDayLastMonth = now()->subMonth();
        $lastMonthOccupiedRooms = Reservation::whereHas('room', function ($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where(function ($query) use ($sameDayLastMonth) {
            // Calculate occupancy for the same day last month using the same logic
            $query->where(function ($q) use ($sameDayLastMonth) {
                // Checked-in guests on that day
                $q->where('status', 'checked_in')
                  ->where('check_in', '<=', $sameDayLastMonth)
                  ->where('check_out', '>=', $sameDayLastMonth);
            })->orWhere(function ($q) use ($sameDayLastMonth) {
                // Confirmed reservations active on that day
                $q->where('status', 'confirmed')
                  ->where('check_in', '<=', $sameDayLastMonth)
                  ->where('check_out', '>=', $sameDayLastMonth);
            });
        })
        ->distinct('room_id')
        ->count('room_id');
        
        $lastMonthOccupancyPct = $totalRooms > 0 ? round(($lastMonthOccupiedRooms / $totalRooms) * 100, 1) : 0;
        
        // Calculate occupancy change from last month
        $occupancyChange = $occupancyPct - $lastMonthOccupancyPct;
        $occupancyChangeFormatted = $occupancyChange >= 0 
            ? '+' . number_format($occupancyChange, 1) 
            : number_format($occupancyChange, 1);

        // Active reservations today (checked_in OR confirmed reservations that include today)
        $activeReservationsToday = Reservation::whereHas('room', function ($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where(function ($query) {
            $query->where('status', 'checked_in')
                  ->orWhere(function ($q) {
                      $q->where('status', 'confirmed')
                        ->where('check_in', '<=', now())
                        ->where('check_out', '>=', now());
                  });
        })
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
        
        // Calculate revenue by fetching reservations and calculating nights in PHP (database-agnostic)
        $reservations = Reservation::whereHas('room', function ($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where('status', 'confirmed')
        ->whereBetween('check_in', [$monthStart, $monthEnd])
        ->with('room')
        ->get();
        
        $monthlyRevenue = $reservations->sum(function ($reservation) {
            $nights = max(1, $reservation->check_in->diffInDays($reservation->check_out));
            return $reservation->room->price * $nights;
        });

        // Booking trends for last 6 months
        $bookingTrends = $this->getBookingTrends($hotelId);

        // Weekly occupancy for current month
        $weeklyOccupancy = $this->getWeeklyOccupancy($hotelId);

        // Revenue trends for last 6 months
        $revenueTrends = $this->getRevenueTrends($hotelId);

        return response()->json([
            'kpis' => [
                'occupancyPct' => $occupancyPct,
                'occupancyChangeFromLastMonth' => $occupancyChange,
                'occupancyChangeFormatted' => $occupancyChangeFormatted,
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
            // Include both checked_in and confirmed reservations
            $occupiedRooms = Reservation::whereHas('room', function ($query) use ($hotelId) {
                $query->where('hotel_id', $hotelId);
            })
            ->where(function ($query) use ($currentWeekStart, $weekEnd) {
                // Checked-in guests (always count as occupied)
                $query->where('status', 'checked_in')
                      // OR confirmed reservations that overlap with this week
                      ->orWhere(function ($q) use ($currentWeekStart, $weekEnd) {
                          $q->where('status', 'confirmed')
                            ->where(function ($subQ) use ($currentWeekStart, $weekEnd) {
                                // Reservation starts in this week
                                $subQ->whereBetween('check_in', [$currentWeekStart, $weekEnd])
                                     // OR reservation ends in this week
                                     ->orWhereBetween('check_out', [$currentWeekStart, $weekEnd])
                                     // OR reservation spans the entire week
                                     ->orWhere(function ($spanQ) use ($currentWeekStart, $weekEnd) {
                                         $spanQ->where('check_in', '<=', $currentWeekStart)
                                               ->where('check_out', '>=', $weekEnd);
                                     });
                            });
                      });
            })
            ->distinct('room_id')
            ->count('room_id');
            
            // Calculate available rooms for this week using room status
            // For weekly view, we'll use the same logic: total - occupied
            // But we could also filter by room status if needed
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
            
            // Calculate revenue by fetching reservations and calculating nights in PHP (database-agnostic)
            $reservations = Reservation::whereHas('room', function ($query) use ($hotelId) {
                $query->where('hotel_id', $hotelId);
            })
            ->where('status', 'confirmed')
            ->whereBetween('check_in', [$monthStart, $monthEnd])
            ->with('room')
            ->get();
            
            $revenue = $reservations->sum(function ($reservation) {
                $nights = max(1, $reservation->check_in->diffInDays($reservation->check_out));
                return $reservation->room->price * $nights;
            });
            
            $months[] = [
                'month' => $monthStart->format('M'),
                'revenue' => (float) $revenue,
            ];
        }
        
        return $months;
    }
}

