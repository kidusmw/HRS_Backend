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
            
            $backup->status = 'success';
            $backup->path = $backupPath;
            $backup->size_bytes = Storage::disk(env('BACKUP_DISK', 'local'))->size($backupPath) ?? null;
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
     * Spatie stores backups in storage/app/{disk}/laravel-backup/
     */
    private function getLatestBackupPath(): ?string
    {
        $disk = env('BACKUP_DISK', 'local');
        $backupPath = storage_path("app/{$disk}/laravel-backup");
        
        if (!is_dir($backupPath)) {
            // Fallback to old location for compatibility
            $backupPath = storage_path('app/backups');
            if (!is_dir($backupPath)) {
                return null;
            }
            $prefix = 'backups/';
        } else {
            $prefix = "{$disk}/laravel-backup/";
        }

        $files = glob($backupPath . '/*.zip');
        if (empty($files)) {
            return null;
        }

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $prefix . basename($files[0]);
    }
}

