<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;

class BackupPolicy
{
    /**
     * Determine whether the user can view any backups.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the backup.
     */
    public function view(User $user, Backup $backup): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create full backups.
     */
    public function createFull(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create hotel backups.
     */
    public function createHotel(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can download backups.
     */
    public function download(User $user, Backup $backup): bool
    {
        return $user->isSuperAdmin();
    }
}
