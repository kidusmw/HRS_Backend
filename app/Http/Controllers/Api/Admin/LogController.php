<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Http\Resources\AuditLogResource;
use App\Enums\UserRole;

class LogController extends Controller
{
    /**
     * Get all audit logs for the admin's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns a paginated list of audit logs for the hotel.
     * Supports filtering by user, action, and date range.
     * All logs are automatically filtered to the admin's hotel.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Start query with hotel scope - only logs for this hotel
        // Explicitly ensure hotel_id is not null and matches the admin's hotel
        // Exclude logs from super admin users - only show logs from hotel staff
        $query = AuditLog::whereNotNull('hotel_id')
            ->where('hotel_id', (int) $hotelId)
            ->whereHas('user', function ($q) {
                // Only show logs from hotel staff - exclude super admin users
                $q->where('role', '!=', UserRole::SUPERADMIN->value);
            })
            ->with(['user', 'hotel']);

        // Filter by user
        if ($userId = $request->integer('userId')) {
            $query->where('user_id', $userId);
        }

        // Filter by action (supports partial match)
        if ($action = $request->string('action')->toString()) {
            $query->where('action', 'like', "%{$action}%");
        }

        // Filter by date range
        if ($from = $request->date('from')) {
            $query->where('timestamp', '>=', $from);
        }

        if ($to = $request->date('to')) {
            // Add time to end of day for inclusive date range
            // Carbon is a library for working with dates and times in PHP
            $toDate = \Carbon\Carbon::parse($to)->endOfDay();
            $query->where('timestamp', '<=', $toDate);
        }

        // Order by most recent first
        $logs = $query->orderBy('timestamp', 'desc')
            ->paginate($request->integer('per_page', 15));

        return AuditLogResource::collection($logs);
    }

    /**
     * Get a specific audit log by ID
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns a single audit log entry.
     * Validates that the log belongs to the admin's hotel.
     */
    public function show(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Get log and ensure it belongs to the admin's hotel
        // Explicitly ensure hotel_id is not null and matches the admin's hotel
        $log = AuditLog::whereNotNull('hotel_id')
            ->where('hotel_id', (int) $hotelId)
            ->with(['user', 'hotel'])
            ->findOrFail($id);

        return new AuditLogResource($log);
    }
}

