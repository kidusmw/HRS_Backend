<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NotificationRead;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->integer('limit', 10);
        $user = $request->user();
        $hotelId = $user->hotel_id;

        // Ensure manager has a hotel scope
        if (!$hotelId) {
            return response()->json(['data' => []]);
        }

        // Manager-relevant notification actions
        $auditLogs = AuditLog::with(['user', 'hotel'])
            ->where('hotel_id', $hotelId)
            ->whereIn('action', [
                'reservation.override',
                'reservation.created',
                'reservation.cancelled',
                'reservation.checked_in',
                'reservation.checked_out',
                'user.created',
                'user.activated',
                'user.deactivated',
                'attendance.recorded',
                'alert.created',
                'hotel.updated',
                'settings.system.updated',
                'Reservation Override',
                'Reservation Created',
                'Reservation Cancelled',
                'User Activated',
                'User Deactivated',
                'System Settings Updated',
            ])
            ->orderBy('timestamp', 'desc')
            ->limit($limit * 2)
            ->get();

        $readLogIds = NotificationRead::where('user_id', $user->id)
            ->pluck('audit_log_id')
            ->toArray();

        $notifications = [];
        foreach ($auditLogs as $log) {
            $message = $this->formatNotificationMessage($log);
            if (!$message) {
                continue;
            }

            $notifications[] = [
                'id' => $log->id,
                'message' => $message,
                'type' => $this->getNotificationType($log->action),
                'status' => in_array($log->id, $readLogIds) ? 'read' : 'unread',
                'timestamp' => $log->timestamp->toIso8601String(),
                'hotelId' => $log->hotel_id,
                'hotelName' => $log->hotel?->name,
            ];
        }

        return response()->json(['data' => $notifications]);
    }

    public function markRead(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        $auditLog = AuditLog::where('id', $id)
            ->where('hotel_id', $hotelId)
            ->firstOrFail();

        NotificationRead::firstOrCreate([
            'user_id' => $user->id,
            'audit_log_id' => $auditLog->id,
        ]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    private function formatNotificationMessage(AuditLog $log): ?string
    {
        $action = $log->action;
        $meta = $log->meta ?? [];

        return match(true) {
            str_contains($action, 'reservation.override') || str_contains($action, 'Reservation Override') => isset($meta['reservation_id'])
                ? "Reservation #{$meta['reservation_id']} overridden"
                : 'Reservation overridden',
            str_contains($action, 'reservation.created') || str_contains($action, 'Reservation Created') => isset($meta['reservation_id'])
                ? "New reservation #{$meta['reservation_id']} created"
                : 'New reservation created',
            str_contains($action, 'reservation.cancelled') || str_contains($action, 'Reservation Cancelled') => isset($meta['reservation_id'])
                ? "Reservation #{$meta['reservation_id']} cancelled"
                : 'Reservation cancelled',
            str_contains($action, 'reservation.checked_in') => isset($meta['reservation_id'])
                ? "Reservation #{$meta['reservation_id']} checked in"
                : 'Reservation checked in',
            str_contains($action, 'reservation.checked_out') => isset($meta['reservation_id'])
                ? "Reservation #{$meta['reservation_id']} checked out"
                : 'Reservation checked out',
            str_contains($action, 'user.created') => isset($meta['user_name'])
                ? "New employee created: {$meta['user_name']}"
                : 'New employee created',
            str_contains($action, 'user.activated') || str_contains($action, 'User Activated') => isset($meta['user_name'])
                ? "Employee activated: {$meta['user_name']}"
                : 'Employee activated',
            str_contains($action, 'user.deactivated') || str_contains($action, 'User Deactivated') => isset($meta['user_name'])
                ? "Employee deactivated: {$meta['user_name']}"
                : 'Employee deactivated',
            str_contains($action, 'attendance.recorded') => isset($meta['user_name'])
                ? "Attendance recorded for {$meta['user_name']}"
                : 'Attendance recorded',
            str_contains($action, 'alert.created') => isset($meta['alert_message'])
                ? $meta['alert_message']
                : 'New alert created',
            str_contains($action, 'hotel.updated') => 'Hotel profile updated',
            str_contains($action, 'settings.system.updated') || str_contains($action, 'System Settings Updated') => 'Hotel settings updated',
            default => null,
        };
    }

    private function getNotificationType(string $action): string
    {
        return match(true) {
            str_contains($action, 'reservation') => 'reservation',
            str_contains($action, 'user') => 'user',
            str_contains($action, 'attendance') => 'attendance',
            str_contains($action, 'alert') => 'alert',
            str_contains($action, 'hotel') => 'hotel',
            str_contains($action, 'settings') => 'settings',
            default => 'system',
        };
    }
}

