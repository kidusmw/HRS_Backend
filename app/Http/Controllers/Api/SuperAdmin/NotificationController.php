<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function index()
    {
        return response()->json([
            ['id' => 1, 'message' => 'Full system backup completed successfully', 'type' => 'backup', 'status' => 'unread', 'timestamp' => now()->toIso8601String()],
        ]);
    }

    public function markRead(int $id)
    {
        return response()->json(['message' => 'Notification marked as read']);
    }
}


