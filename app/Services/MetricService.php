<?php

namespace App\Services;

use App\Models\User;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Reservation;
use App\Enums\UserRole;

class MetricService
{
    /**
     * Get dashboard metrics for SuperAdmin
     */
    public function getDashboardMetrics(): array
    {
        // Get counts per role enum value
        $usersByRole = [];
        foreach (UserRole::cases() as $role) {
            $usersByRole[$role->value] = User::where('role', $role)->count();
        }

        // Normalize role keys to match frontend expectations
        $normalized = [];
        foreach (UserRole::cases() as $role) {
            $key = $role->value;
            $normalized[$key === 'superadmin' ? 'super_admin' : $key] = $usersByRole[$key] ?? 0;
        }

        // Calculate room occupancy
        $totalRooms = Room::count();
        $occupiedRooms = Reservation::where('status', 'confirmed')
            ->where('check_in', '<=', now())
            ->where('check_out', '>=', now())
            ->distinct('room_id')
            ->count('room_id');
        $availableRooms = $totalRooms - $occupiedRooms;

        // Get booking trends for last 6 months
        $bookingTrends = $this->getBookingTrends();

        // Get room occupancy trends for last 6 months
        $occupancyTrends = $this->getOccupancyTrends();

        return [
            'hotels' => Hotel::count(),
            'usersByRole' => $normalized,
            'totalBookings' => Reservation::count(),
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
     */
    private function getBookingTrends(): array
    {
        $months = [];
        $now = now();
        
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            $bookings = Reservation::whereBetween('reservations.created_at', [$monthStart, $monthEnd])
                ->count();
            
            $revenue = Reservation::whereBetween('reservations.created_at', [$monthStart, $monthEnd])
                ->join('rooms', 'reservations.room_id', '=', 'rooms.id')
                ->sum('rooms.price');
            
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
     */
    private function getOccupancyTrends(): array
    {
        $months = [];
        $now = now();
        $totalRooms = Room::count();
        
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            // Count rooms that had active reservations during this month
            $occupiedRooms = Reservation::where('status', 'confirmed')
                ->where(function ($query) use ($monthStart, $monthEnd) {
                    $query->where(function ($q) use ($monthStart, $monthEnd) {
                        // Reservation starts in this month
                        $q->whereBetween('check_in', [$monthStart, $monthEnd]);
                    })->orWhere(function ($q) use ($monthStart, $monthEnd) {
                        // Reservation ends in this month
                        $q->whereBetween('check_out', [$monthStart, $monthEnd]);
                    })->orWhere(function ($q) use ($monthStart, $monthEnd) {
                        // Reservation spans the entire month
                        $q->where('check_in', '<=', $monthStart)
                          ->where('check_out', '>=', $monthEnd);
                    });
                })
                ->distinct('room_id')
                ->count('room_id');
            
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

