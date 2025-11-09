<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Hotel;
use App\Models\User;
use App\Models\Room;
use App\Models\Reservation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class BackupService
{
    /**
     * Run a full system backup using Spatie Backup
     */
    public function runFullBackup(User $user): Backup
    {
        $backup = Backup::create([
            'type' => 'full',
            'status' => 'queued',
            'created_by' => $user->id,
        ]);

        try {
            $backup->status = 'running';
            $backup->save();

            // Run Spatie backup command
            Artisan::call('backup:run');

            // Get the latest backup file from Spatie
            $backupPath = $this->getLatestBackupPath();
            
            if (!$backupPath) {
                throw new \Exception('Backup file not found after running backup command');
            }
            
            // Use the configured backup disk (usually 'local' which points to storage/app/private)
            $disk = env('BACKUP_DISK', 'local');
            
            $backup->status = 'success';
            $backup->path = $backupPath;
            
            // Only get size if file exists
            if (Storage::disk($disk)->exists($backupPath)) {
                $backup->size_bytes = Storage::disk($disk)->size($backupPath);
            } else {
                $backup->size_bytes = null;
            }
            
            $backup->save();

            AuditLogger::logBackupCompleted('full', $backupPath, null, $user);
        } catch (\Exception $e) {
            $backup->status = 'failed';
            $backup->save();
            
            throw $e;
        }

        return $backup;
    }

    /**
     * Generate a hotel-specific JSON export
     */
    public function runHotelBackup(int $hotelId, User $user): Backup
    {
        $hotel = Hotel::findOrFail($hotelId);
        
        $backup = Backup::create([
            'type' => 'hotel',
            'hotel_id' => $hotelId,
            'status' => 'queued',
            'created_by' => $user->id,
        ]);

        try {
            $backup->status = 'running';
            $backup->save();

            // Collect hotel-specific data
            $data = [
                'hotel' => $hotel->toArray(),
                'rooms' => $hotel->rooms()->get()->map(fn($room) => $room->toArray()),
                'reservations' => Reservation::whereHas('room', fn($q) => $q->where('hotel_id', $hotelId))
                    ->with(['room', 'user'])
                    ->get()
                    ->map(fn($res) => $res->toArray()),
                'users' => $hotel->users()->get()->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'active' => $user->active,
                    'created_at' => $user->created_at?->toIso8601String(),
                ]),
                'exported_at' => now()->toIso8601String(),
            ];

            // Save JSON to storage
            $filename = "hotel-{$hotelId}-" . now()->format('Y-m-d-His') . '.json';
            $path = "hotel-exports/{$filename}";
            
            Storage::disk('local')->put($path, json_encode($data, JSON_PRETTY_PRINT));
            
            $backup->status = 'success';
            $backup->path = $path;
            $backup->size_bytes = Storage::disk('local')->size($path);
            $backup->save();

            AuditLogger::logBackupCompleted('hotel', $path, $hotelId, $user);
        } catch (\Exception $e) {
            $backup->status = 'failed';
            $backup->save();
            
            throw $e;
        }

        return $backup;
    }

    /**
     * Get the latest backup file path from Spatie
     * Spatie stores backups based on the configured disk
     */
    private function getLatestBackupPath(): ?string
    {
        $disk = env('BACKUP_DISK', 'local');
        
        // Check multiple possible locations where Spatie might store backups
        $possiblePaths = [
            // Default Spatie location for 'private' disk
            ['path' => storage_path('app/private/Laravel'), 'prefix' => 'private/Laravel/'],
            // Default Spatie location for 'local' disk
            ['path' => storage_path("app/{$disk}/Laravel"), 'prefix' => "{$disk}/Laravel/"],
            // Alternative location
            ['path' => storage_path("app/{$disk}/laravel-backup"), 'prefix' => "{$disk}/laravel-backup/"],
            // Fallback to old location
            ['path' => storage_path('app/backups'), 'prefix' => 'backups/'],
        ];

        foreach ($possiblePaths as $pathConfig) {
            $backupPath = $pathConfig['path'];
            if (is_dir($backupPath)) {
                $files = glob($backupPath . '/*.zip');
                if (!empty($files)) {
                    // Sort by modification time, newest first
                    usort($files, function ($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    
                    $filename = basename($files[0]);
                    $prefix = $pathConfig['prefix'];
                    
                    // If using 'local' disk and path is in private/Laravel, 
                    // the path should be relative to the disk root (Laravel/filename)
                    if ($prefix === 'private/Laravel/' && $disk === 'local') {
                        return 'Laravel/' . $filename;
                    }
                    
                    return $prefix . $filename;
                }
            }
        }

        return null;
    }
}

