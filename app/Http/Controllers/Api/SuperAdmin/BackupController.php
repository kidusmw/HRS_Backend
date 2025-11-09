<?php

namespace App\Http\Controllers\Api\SuperAdmin;

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

    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $page = $request->integer('page', 1);
        
        // Get backups with hotel and creator, order by created_at descending, paginate the results
        $backups = Backup::with(['hotel', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        return BackupResource::collection($backups);
    }

    public function runFull()
    {
        AuditLogger::logBackupStarted('full', null, auth()->user());
        
        $backup = $this->backupService->runFullBackup(auth()->user());
        
        return new BackupResource($backup);
    }

    public function runHotel(int $hotelId)
    {
        AuditLogger::logBackupStarted('hotel', $hotelId, auth()->user());
        
        $backup = $this->backupService->runHotelBackup($hotelId, auth()->user());
        
        return new BackupResource($backup);
    }

    public function download(int $id)
    {
        $backup = Backup::findOrFail($id);
        
        if ($backup->status !== 'success' || !$backup->path) {
            return response()->json(['message' => 'Backup not available'], 404);
        }

        // Use the configured backup disk for full backups, 'local' for hotel backups
        // The 'local' disk root is storage/app/private, so paths are relative to that
        $disk = $backup->type === 'full' ? env('BACKUP_DISK', 'local') : 'local';
        
        if (!Storage::disk($disk)->exists($backup->path)) {
            return response()->json(['message' => 'Backup file not found'], 404);
        }

        return Storage::disk($disk)->download($backup->path);
    }
}


