<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backup;
use App\Services\BackupService;
use App\Services\AuditLogger;
use App\Http\Resources\BackupResource;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $backupService
    ) {}

    /**
     * Get all backups for the admin's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns a paginated list of backups for the hotel.
     * Only shows hotel-type backups for the admin's hotel.
     * Supports pagination.
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

        // Only show hotel backups for this hotel
        $backups = Backup::where('hotel_id', $hotelId)
            ->where('type', 'hotel') // Only hotel backups, not full system backups
            ->with(['hotel', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return BackupResource::collection($backups);
    }

    /**
     * Create a new hotel backup
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Creates a hotel-specific backup for the admin's hotel.
     * The backup includes hotel data, rooms, reservations, and users.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Log backup initiation
        AuditLogger::logBackupStarted('hotel', $hotelId, $user);

        // Create hotel backup
        $backup = $this->backupService->runHotelBackup($hotelId, $user);

        return new BackupResource($backup);
    }

    /**
     * Download a backup file
     * @param int $id
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     * 
     * Downloads a backup file.
     * Validates that the backup belongs to the admin's hotel.
     */
    public function download(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Get backup and ensure it belongs to the admin's hotel
        $backup = Backup::where('hotel_id', $hotelId)
            ->where('type', 'hotel')
            ->findOrFail($id);

        if ($backup->status !== 'success' || !$backup->path) {
            return response()->json([
                'message' => 'Backup not available for download'
            ], 404);
        }

        // Hotel backups are stored on the 'local' disk
        if (!Storage::disk('local')->exists($backup->path)) {
            return response()->json([
                'message' => 'Backup file not found'
            ], 404);
        }

        return Storage::disk('local')->download($backup->path);
    }
}

