<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Http\Resources\AuditLogResource;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with(['user', 'hotel']);

        if ($userId = $request->integer('userId')) {
            $query->where('user_id', $userId);
        }

        if ($hotelId = $request->integer('hotelId')) {
            $query->where('hotel_id', $hotelId);
        }

        if ($action = $request->string('action')->toString()) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($from = $request->date('from')) {
            $query->where('timestamp', '>=', $from);
        }

        if ($to = $request->date('to')) {
            $query->where('timestamp', '<=', $to);
        }

        $logs = $query->orderBy('timestamp', 'desc')->paginate($request->integer('per_page', 15));

        return AuditLogResource::collection($logs);
    }

    public function show(int $id)
    {
        $log = AuditLog::with(['user', 'hotel'])->findOrFail($id);
        return new AuditLogResource($log);
    }
}


