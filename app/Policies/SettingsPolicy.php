<?php

namespace App\Policies;

use App\Models\User;

class SettingsPolicy
{
    /**
     * Determine whether the user can view system settings.
     */
    public function viewSystem(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update system settings.
     */
    public function updateSystem(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view hotel settings.
     */
    public function viewHotel(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Determine whether the user can update hotel settings.
     */
    public function updateHotel(User $user, ?int $hotelId = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Admin can only update settings for their own hotel
        return $user->isAdmin() && $user->hotel_id === $hotelId;
    }
}
