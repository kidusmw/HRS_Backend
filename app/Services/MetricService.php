<?php

namespace App\Services;

use App\Models\User;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Reservation;
use App\Enums\UserRole;
use App\Enums\RoomStatus;
use Carbon\Carbon;

class MetricService
{
    /**
     * Get dashboard metrics for SuperAdmin
     * 
     * Calculates system-wide metrics including:
     * - Total hotels, users by role
     * - Total active bookings (excluding cancelled)
     * - Current room occupancy (checked_in + confirmed active reservations)
     * - Available rooms (considering room status)
     * - Booking and occupancy trends over last 6 months
     */
    public function getDashboardMetrics(): array
    {
        // Get counts per role enum value
        $usersByRole = [];
        foreach (UserRole::cases() as $role) {
            $usersByRole[$role->value] = User::where('role', $role)->count();
        }

        // Normalize role keys to match frontend expectations
        // Frontend expects 'super_admin' but enum uses 'superadmin'
        $normalized = [];
        foreach (UserRole::cases() as $role) {
            $key = $role->value;
            $normalized[$key === 'superadmin' ? 'super_admin' : $key] = $usersByRole[$key] ?? 0;
        }

        // Calculate current room occupancy
        // A room is occupied if it has:
        // 1. A checked_in reservation (guest is currently in the room), OR
        // 2. A confirmed reservation that is currently active (check_in <= today <= check_out)
        $today = Carbon::today();
        $occupiedRooms = Reservation::where(function ($query) use ($today) {
            // Currently checked-in guests (always count as occupied)
            $query->where('status', 'checked_in')
                  // OR confirmed reservations that are currently active
                  ->orWhere(function ($q) use ($today) {
                      $q->where('status', 'confirmed')
                        ->whereDate('check_in', '<=', $today)
                        ->whereDate('check_out', '>=', $today);
                  });
        })
        ->distinct('room_id')
        ->count('room_id');

        // Calculate available rooms
        // A room is available only if:
        // 1. Room status is 'available' (not maintenance/unavailable/occupied), AND
        // 2. The room doesn't have an active reservation (checked_in OR confirmed with today in date range)
        $roomsWithActiveReservations = Reservation::where(function ($query) use ($today) {
            // Currently checked-in guests
            $query->where('status', 'checked_in')
                  // OR confirmed reservations that are currently active
                  ->orWhere(function ($q) use ($today) {
                      $q->where('status', 'confirmed')
                        ->whereDate('check_in', '<=', $today)
                        ->whereDate('check_out', '>=', $today);
                  });
        })
        ->pluck('room_id')
        ->unique()
        ->toArray();
        
        $availableRooms = Room::where('status', RoomStatus::AVAILABLE)
            ->whereNotIn('id', $roomsWithActiveReservations)
            ->count();

        // Total bookings: count all reservations except cancelled ones
        // This represents all bookings that were made, regardless of current status
        $totalBookings = Reservation::where('status', '!=', 'cancelled')->count();

        // Get booking trends for last 6 months
        $bookingTrends = $this->getBookingTrends();

        // Get room occupancy trends for last 6 months
        $occupancyTrends = $this->getOccupancyTrends();

        return [
            'hotels' => Hotel::count(),
            'usersByRole' => $normalized,
            'totalBookings' => $totalBookings,
            'rooms' => [
                'available' => max(0, $availableRooms),
                'occupied' => $occupiedRooms,
            ],
            'bookingTrends' => $bookingTrends,
            'occupancyTrends' => $occupancyTrends,
        ];
    }

    /**
     * Get booking trends for the last 6 months
     * 
     * For each month, calculates:
     * - Number of bookings created in that month (excluding cancelled)
     * - Revenue generated from reservations that have nights in that month
     * 
     * Revenue calculation:
     * - Finds all reservations that overlap with the month (regardless of when created)
     * - Calculates revenue as: room_price × number_of_nights_in_month
     * - Only includes confirmed, checked_in, or checked_out reservations (excludes cancelled/pending)
     * 
     * Example: If a reservation runs from Jan 28 to Feb 3, it contributes:
     * - 4 nights to January revenue (Jan 28-31)
     * - 3 nights to February revenue (Feb 1-3)
     */
    private function getBookingTrends(): array
    {
        $months = [];
        $now = now();
        
        // Iterate through last 6 months (from 5 months ago to current month)
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            // Count bookings created in this month (excluding cancelled)
            $bookings = Reservation::whereBetween('created_at', [$monthStart, $monthEnd])
                ->where('status', '!=', 'cancelled')
                ->count();
            
            // Calculate revenue for this month
            // Revenue includes all reservations that have nights in this month,
            // regardless of when the reservation was created
            // A reservation overlaps with a month if: check_in <= monthEnd AND check_out >= monthStart
            $reservations = Reservation::where('check_in', '<=', $monthEnd)
                ->where('check_out', '>=', $monthStart)
                ->whereIn('status', ['confirmed', 'checked_in', 'checked_out']) // Only count active/completed reservations
                ->with('room')
                ->get();
            
            // Calculate revenue based on nights that fall within this month
            $revenue = $reservations->sum(function ($reservation) use ($monthStart, $monthEnd) {
                $roomPrice = $reservation->room->price ?? 0;
                
                // Normalize dates to start of day for accurate date comparisons
                $resCheckIn = Carbon::parse($reservation->check_in)->startOfDay();
                $resCheckOut = Carbon::parse($reservation->check_out)->startOfDay();
                $monthStartDay = $monthStart->copy()->startOfDay();
                $monthEndDay = $monthEnd->copy()->startOfDay();
                
                // Calculate the overlap between reservation dates and this month
                // Overlap starts at the later of: reservation check_in or month start
                // Overlap ends at the earlier of: reservation check_out or month end
                $overlapStart = $resCheckIn->max($monthStartDay);
                $overlapEnd = $resCheckOut->min($monthEndDay);
                
                // Calculate number of nights in the overlap period
                // diffInDays returns the number of days between two dates
                // If same day (check-in = check-out), that's 1 night
                // Otherwise, diffInDays gives the number of nights
                $nightsDiff = $overlapStart->diffInDays($overlapEnd);
                $nights = $overlapStart->eq($overlapEnd) ? 1 : max(1, $nightsDiff);
                
                // Revenue = room price × number of nights in this month
                return $roomPrice * $nights;
            });
            
            $months[] = [
                'month' => $monthStart->format('M'),
                'bookings' => $bookings,
                'revenue' => (float) $revenue,
            ];
        }
        
        return $months;
    }

    /**
     * Get room occupancy trends for the last 6 months
     * 
     * For each month, calculates:
     * - Number of rooms that had active reservations during that month
     * - Number of available rooms (total - occupied)
     * 
     * Occupancy calculation logic:
     * - A room is considered occupied if it has a reservation that overlaps with the month
     * - Includes both 'checked_in' and 'confirmed' status reservations
     * - A reservation overlaps with a month if:
     *   1. Reservation starts in this month (check_in between monthStart and monthEnd), OR
     *   2. Reservation ends in this month (check_out between monthStart and monthEnd), OR
     *   3. Reservation spans the entire month (check_in <= monthStart AND check_out >= monthEnd)
     * 
     * Available rooms calculation:
     * - Total rooms minus occupied rooms
     * - Note: This is a simplified calculation. For more accuracy, should also consider
     *   room status (maintenance, unavailable), but for trend analysis this is acceptable
     */
    private function getOccupancyTrends(): array
    {
        $months = [];
        $now = now();
        $totalRooms = Room::count();
        
        // Iterate through last 6 months (from 5 months ago to current month)
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            // Count rooms that had active reservations during this month
            // For historical months, we need to count all reservations that overlapped the month,
            // regardless of their current status (as long as they weren't cancelled)
            // This ensures accurate historical occupancy data
            // Normalize dates to start of day for accurate date comparisons
            $monthStartDay = $monthStart->copy()->startOfDay();
            $monthEndDay = $monthEnd->copy()->startOfDay();
            
            // Find all reservations that overlapped this month (based on check_in/check_out dates)
            // Exclude only cancelled reservations - all other statuses count if they overlapped the month
            $occupiedRooms = Reservation::where('status', '!=', 'cancelled')
                ->where(function ($query) use ($monthStartDay, $monthEndDay) {
                    $query->where(function ($q) use ($monthStartDay, $monthEndDay) {
                        // Case 1: Reservation starts in this month
                        // Use whereDate for accurate date-only comparisons
                        $q->whereDate('check_in', '>=', $monthStartDay)
                          ->whereDate('check_in', '<=', $monthEndDay);
                    })->orWhere(function ($q) use ($monthStartDay, $monthEndDay) {
                        // Case 2: Reservation ends in this month
                        $q->whereDate('check_out', '>=', $monthStartDay)
                          ->whereDate('check_out', '<=', $monthEndDay);
                    })->orWhere(function ($q) use ($monthStartDay, $monthEndDay) {
                        // Case 3: Reservation spans the entire month
                        // (starts before or on month start, ends after or on month end)
                        $q->whereDate('check_in', '<=', $monthStartDay)
                          ->whereDate('check_out', '>=', $monthEndDay);
                    });
                })
                ->distinct('room_id')
                ->count('room_id');
            
            // Available rooms = total rooms - occupied rooms
            // Using max(0, ...) to ensure we never return negative values
            $availableRooms = max(0, $totalRooms - $occupiedRooms);
            
            $months[] = [
                'month' => $monthStart->format('M'),
                'occupied' => $occupiedRooms,
                'available' => $availableRooms,
            ];
        }
        
        return $months;
    }
}

