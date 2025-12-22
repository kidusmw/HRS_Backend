<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Enums\UserRole;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Get receptionist activities for the manager's hotel
     * Returns paginated list of audit logs from receptionist users
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Query audit logs from receptionist users in this hotel
        $query = AuditLog::with(['user', 'hotel'])
            ->where('hotel_id', $hotelId)
            ->whereHas('user', function ($q) {
                $q->where('role', UserRole::RECEPTIONIST->value);
            })
            ->orderBy('timestamp', 'desc');

        // Filter by booking/reservation ID if provided
        if ($bookingId = $request->integer('booking_id')) {
            $query->where(function ($q) use ($bookingId) {
                $q->whereJsonContains('meta->reservation_id', $bookingId)
                  ->orWhereJsonContains('meta->booking_id', $bookingId);
            });
        }

        // Filter by action type if provided
        if ($action = $request->string('action')->toString()) {
            $query->where('action', 'like', "%{$action}%");
        }

        // Filter by date range
        if ($from = $request->date('from')) {
            $query->where('timestamp', '>=', $from);
        }

        if ($to = $request->date('to')) {
            $query->where('timestamp', '<=', $to);
        }

        $perPage = $request->integer('per_page', 10);
        $logs = $query->paginate($perPage);

        // Transform logs to activity format
        $activities = $logs->map(function ($log) {
            return $this->transformActivity($log);
        });

        return response()->json([
            'data' => $activities,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'from' => $logs->firstItem(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'to' => $logs->lastItem(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Transform audit log to activity format
     */
    private function transformActivity(AuditLog $log): array
    {
        $action = $log->action;
        $meta = $log->meta ?? [];
        $user = $log->user;

        // Determine activity type and description
        $activityType = $this->getActivityType($action);
        $description = $this->getActivityDescription($action, $meta);

        return [
            'id' => $log->id,
            'type' => $activityType,
            'action' => $action,
            'description' => $description,
            'receptionist' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'reservation_id' => $meta['reservation_id'] ?? $meta['booking_id'] ?? null,
            'room_id' => $meta['room_id'] ?? null,
            'meta' => $meta,
            'timestamp' => $log->timestamp->toIso8601String(),
            'created_at' => $log->timestamp->toIso8601String(),
        ];
    }

    /**
     * Get activity type from action string
     */
    private function getActivityType(string $action): string
    {
        return match(true) {
            str_contains($action, 'reservation') || str_contains($action, 'booking') => 'reservation',
            str_contains($action, 'room') => 'room',
            str_contains($action, 'check_in') || str_contains($action, 'check-in') => 'check_in',
            str_contains($action, 'check_out') || str_contains($action, 'check-out') => 'check_out',
            str_contains($action, 'cancel') => 'cancellation',
            str_contains($action, 'confirm') => 'confirmation',
            default => 'other',
        };
    }

    /**
     * Get human-readable activity description
     */
    private function getActivityDescription(string $action, array $meta): string
    {
        $reservationId = $meta['reservation_id'] ?? $meta['booking_id'] ?? null;
        $reservationRef = $reservationId ? "Booking #{$reservationId}" : 'Reservation';

        return match(true) {
            str_contains($action, 'walk-in') || str_contains($action, 'walk_in') || (str_contains($action, 'created') && isset($meta['is_walk_in'])) => 
                "Created walk-in {$reservationRef}",
            str_contains($action, 'check_in') || str_contains($action, 'check-in') => 
                "Checked in {$reservationRef}",
            str_contains($action, 'check_out') || str_contains($action, 'check-out') => 
                "Checked out {$reservationRef}",
            str_contains($action, 'confirm') => 
                "Confirmed {$reservationRef}",
            str_contains($action, 'cancel') => 
                "Cancelled {$reservationRef}",
            str_contains($action, 'room') && str_contains($action, 'status') => 
                "Updated room status" . ($meta['room_id'] ? " (Room #{$meta['room_id']})" : ''),
            str_contains($action, 'created') && str_contains($action, 'reservation') => 
                "Created {$reservationRef}",
            default => ucfirst(str_replace(['_', '.'], ' ', $action)),
        };
    }
}
