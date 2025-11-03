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

        return [
            'hotels' => Hotel::count(),
            'usersByRole' => $normalized,
            'totalBookings' => Reservation::count(),
            'rooms' => [
                'available' => max(0, $availableRooms),
                'occupied' => $occupiedRooms,
            ],
        ];
    }
}

