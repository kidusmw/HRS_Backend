<?php

namespace App\Http\Controllers\Api\Admin;

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

        // Ensure admin has a hotel scope
        if (!$hotelId) {
            return response()->json(['data' => []]);
        }

        $auditLogs = AuditLog::with(['user', 'hotel'])
            ->where('hotel_id', $hotelId)
            ->whereIn('action', [
                'backup.completed',
                'backup.started',
                'user.created',
                'user.activated',
                'user.deactivated',
                'hotel.updated',
                'settings.system.updated',
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
            str_contains($action, 'backup.completed') => 'Hotel backup completed successfully',
            str_contains($action, 'backup.started') => 'Hotel backup started',
            str_contains($action, 'user.created') => isset($meta['user_name'])
                ? "New staff created: {$meta['user_name']}"
                : 'New staff created',
            str_contains($action, 'user.activated') || str_contains($action, 'User Activated') => isset($meta['user_name'])
                ? "User activated: {$meta['user_name']}"
                : 'User activated',
            str_contains($action, 'user.deactivated') || str_contains($action, 'User Deactivated') => isset($meta['user_name'])
                ? "User deactivated: {$meta['user_name']}"
                : 'User deactivated',
            str_contains($action, 'hotel.updated') => 'Hotel profile updated',
            str_contains($action, 'settings.system.updated') || str_contains($action, 'System Settings Updated') => 'Hotel settings updated',
            default => null,
        };
    }

    private function getNotificationType(string $action): string
    {
        return match(true) {
            str_contains($action, 'backup') => 'backup',
            str_contains($action, 'user') => 'user',
            str_contains($action, 'hotel') => 'hotel',
            str_contains($action, 'settings') => 'settings',
            default => 'system',
        };
    }
}

