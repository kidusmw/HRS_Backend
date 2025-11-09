<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    /**
     * Log an action to the audit log
     */
    public static function log(string $action, ?User $user = null, ?int $hotelId = null, ?array $meta = null): AuditLog
    {
        return AuditLog::create([
            'timestamp' => now(),
            'user_id' => $user?->id ?? auth()->id() ?? 0,
            'action' => $action,
            'hotel_id' => $hotelId,
            'meta' => $meta,
        ]);
    }

    /**
     * Log user creation
     */
    public static function logUserCreated(User $user, ?User $createdBy = null): void
    {
        static::log('user.created', $createdBy ?? auth()->user(), $user->hotel_id, [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'role' => $user->role->value,
        ]);
    }

    /**
     * Log user update
     */
    public static function logUserUpdated(User $user, array $changes, ?User $updatedBy = null): void
    {
        static::log('user.updated', $updatedBy ?? auth()->user(), $user->hotel_id, [
            'user_id' => $user->id,
            'changes' => $changes,
        ]);
    }

    /**
     * Log hotel creation
     */
    public static function logHotelCreated($hotel, ?User $createdBy = null): void
    {
        static::log('hotel.created', $createdBy ?? auth()->user(), $hotel->id, [
            'hotel_id' => $hotel->id,
            'hotel_name' => $hotel->name,
        ]);
    }

    /**
     * Log hotel update
     */
    public static function logHotelUpdated($hotel, array $changes, ?User $updatedBy = null): void
    {
        static::log('hotel.updated', $updatedBy ?? auth()->user(), $hotel->id, [
            'hotel_id' => $hotel->id,
            'changes' => $changes,
        ]);
    }

    /**
     * Log backup initiation
     */
    public static function logBackupStarted(string $type, ?int $hotelId = null, ?User $user = null): void
    {
        static::log('backup.started', $user ?? auth()->user(), $hotelId, [
            'type' => $type,
        ]);
    }

    /**
     * Log backup completion
     */
    public static function logBackupCompleted(string $type, string $path, ?int $hotelId = null, ?User $user = null): void
    {
        static::log('backup.completed', $user ?? auth()->user(), $hotelId, [
            'type' => $type,
            'path' => $path,
        ]);
    }
}

