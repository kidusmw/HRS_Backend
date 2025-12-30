<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Media
{
    /**
     * Disk name used for user-uploaded media assets.
     */
    public static function diskName(): string
    {
        return (string) config('filesystems.media_disk', 'public');
    }

    /**
     * Filesystem disk used for user-uploaded media assets.
     */
    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(self::diskName());
    }

    /**
     * Build an absolute URL for a stored media path (or return the URL as-is).
     */
    public static function url(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        // Already an absolute URL or data URL (legacy values).
        if (
            str_starts_with($value, 'http://') ||
            str_starts_with($value, 'https://') ||
            str_starts_with($value, 'data:')
        ) {
            return $value;
        }

        $generated = self::disk()->url($value);

        // Some disks (e.g. local public) may return a relative path.
        if (str_starts_with($generated, 'http://') || str_starts_with($generated, 'https://')) {
            return $generated;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        return $appUrl . $generated;
    }

    /**
     * Delete the stored object if a value is present.
     *
     * For cloud drivers, exists() may be slow or unsupported; delete() is safe
     * to call and we intentionally swallow errors for "missing" objects.
     */
    public static function deleteIfPresent(?string $value): void
    {
        if (!$value) {
            return;
        }

        // Do not attempt to delete remote URLs or legacy data URLs.
        if (
            str_starts_with($value, 'http://') ||
            str_starts_with($value, 'https://') ||
            str_starts_with($value, 'data:')
        ) {
            return;
        }

        try {
            self::disk()->delete($value);
        } catch (\Throwable $e) {
            // Intentionally ignore (e.g. missing file/object).
        }
    }
}


