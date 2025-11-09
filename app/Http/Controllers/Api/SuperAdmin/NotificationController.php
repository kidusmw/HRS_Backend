<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NotificationRead;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->integer('limit', 10);
        $userId = auth()->id();
        
        // Get recent audit logs that should be shown as notifications
        $auditLogs = AuditLog::with(['user', 'hotel'])
            ->whereIn('action', [
                'backup.completed',
                'backup.started',
                'user.created',
                'user.activated',
                'user.deactivated',
                'hotel.created',
                'hotel.updated',
                'settings.system.updated',
                'User Activated',
                'User Deactivated',
                'System Settings Updated',
            ])
            ->orderBy('timestamp', 'desc')
            ->limit($limit * 2) // Get more to filter
            ->get();
        
        // Get read notification IDs for this user
        $readLogIds = NotificationRead::where('user_id', $userId)
            ->pluck('audit_log_id')
            ->toArray();
        
        // Transform audit logs to notifications
        $notifications = [];
        foreach ($auditLogs as $log) {
            if (count($notifications) >= $limit) {
                break;
            }
            
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
        $userId = auth()->id();
        
        // Verify the audit log exists
        $auditLog = AuditLog::findOrFail($id);
        
        // Mark as read (create if doesn't exist)
        NotificationRead::firstOrCreate([
            'user_id' => $userId,
            'audit_log_id' => $id,
        ]);
        
        return response()->json(['message' => 'Notification marked as read']);
    }

    private function formatNotificationMessage(AuditLog $log): ?string
    {
        $action = $log->action;
        $meta = $log->meta ?? [];
        
        return match(true) {
            str_contains($action, 'backup.completed') => 'Full system backup completed successfully',
            str_contains($action, 'backup.started') => 'Backup process started',
            str_contains($action, 'user.created') => isset($meta['user_name']) 
                ? "New user created: {$meta['user_name']}" 
                : 'New user created',
            str_contains($action, 'user.activated') || str_contains($action, 'User Activated') => isset($meta['user_name'])
                ? "User activated: {$meta['user_name']}"
                : 'User activated',
            str_contains($action, 'user.deactivated') || str_contains($action, 'User Deactivated') => isset($meta['user_name'])
                ? "User deactivated: {$meta['user_name']}"
                : 'User deactivated',
            str_contains($action, 'hotel.created') => isset($meta['hotel_name'])
                ? "New hotel created: {$meta['hotel_name']}"
                : 'New hotel created',
            str_contains($action, 'hotel.updated') => isset($meta['hotel_id'])
                ? 'Hotel information updated'
                : 'Hotel updated',
            str_contains($action, 'settings.system.updated') || str_contains($action, 'System Settings Updated') => 'System settings updated',
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


