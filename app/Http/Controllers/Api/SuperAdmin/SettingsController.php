<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use App\Models\HotelSetting;
use App\Models\Hotel;
use App\Http\Resources\SystemSettingsResource;
use App\Http\Requests\SuperAdmin\UpdateSystemSettingsRequest;
use App\Http\Requests\SuperAdmin\UpdateHotelSettingsRequest;
use App\Services\AuditLogger;
use App\Support\Media;

class SettingsController extends Controller
{
    public function getSystem()
    {
        // Get the system logo path (stored as file path, not URL)
        $logoPath = SystemSetting::getValue('system_logo_path');
        $resolvedLogoUrl = null;
        if ($logoPath) {
            // Validate that it's a file path, not a data URL or external URL
            // If it's a data URL or external URL, ignore it (old invalid data)
            if (!str_starts_with($logoPath, 'data:image/') && !str_starts_with($logoPath, 'http://') && !str_starts_with($logoPath, 'https://')) {
                // Always treat as storage path and generate URL
                $resolvedLogoUrl = Media::url($logoPath);
            }
        }

        $settings = [
            'systemName' => SystemSetting::getValue('system_name', config('app.name')),
            'systemLogoUrl' => $resolvedLogoUrl,
            'defaultCurrency' => 'ETB', // Always ETB
            'defaultTimezone' => 'UTC', // Always UTC
            // Payment options
            // Chapa is always enabled for customers (no toggle)
            'chapaEnabled' => true,
            'stripeEnabled' => (bool) SystemSetting::getValue('stripe_enabled', false),
            'telebirrEnabled' => (bool) SystemSetting::getValue('telebirr_enabled', false),
        ];

        return new SystemSettingsResource($settings);
    }

    public function updateSystem(UpdateSystemSettingsRequest $request)
    {
        // Get current settings before update
        $oldLogoPath = SystemSetting::getValue('system_logo_path');
        // Only generate URL if it's a valid file path (not a data URL or external URL)
        $oldLogoUrl = null;
        if ($oldLogoPath && !str_starts_with($oldLogoPath, 'data:image/') && !str_starts_with($oldLogoPath, 'http://') && !str_starts_with($oldLogoPath, 'https://')) {
            $oldLogoUrl = Media::url($oldLogoPath);
        }
        
        $oldSettings = [
            'systemName' => SystemSetting::getValue('system_name', config('app.name')),
            'systemLogoUrl' => $oldLogoUrl,
            'defaultCurrency' => 'ETB',
            'defaultTimezone' => 'UTC',
            // Chapa is always enabled for customers (no toggle)
            'chapaEnabled' => true,
            'stripeEnabled' => (bool) SystemSetting::getValue('stripe_enabled', false),
            'telebirrEnabled' => (bool) SystemSetting::getValue('telebirr_enabled', false),
        ];

        $validated = $request->validated();
        
        // Convert string booleans from FormData to actual booleans
        $validated = array_map(function ($value) {
            if (is_string($value) && ($value === 'true' || $value === 'false')) {
                return $value === 'true';
            }
            return $value;
        }, $validated);
        
        $changes = [];

        // Handle logo file upload (only file uploads, no URL input)
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            
            // Validate file is actually a valid uploaded file
            if (!$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file upload'
                ], 422);
            }
            
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'system_logo.' . $extension;
            $path = $file->storeAs('system-logos', $filename, Media::diskName());

            // Delete old logo file if it exists and is a valid file path (not a data URL)
            if ($oldLogoPath && !str_starts_with($oldLogoPath, 'data:image/') && !str_starts_with($oldLogoPath, 'http://') && !str_starts_with($oldLogoPath, 'https://')) {
                // Only delete if it's a storage file path
                try {
                    Media::deleteIfPresent($oldLogoPath);
                } catch (\Exception $e) {
                    // Ignore errors if file doesn't exist
                }
            }

            $newLogoUrl = Media::url($path);
            $changes['systemLogoUrl'] = [
                'old' => $oldLogoUrl,
                'new' => $newLogoUrl,
            ];
            // Store only the file path, not URL or data URL
            SystemSetting::setValue('system_logo_path', $path);
            
            // Clean up old system_logo_url key if it exists (one-time migration)
            $oldLogoUrlKey = SystemSetting::where('key', 'system_logo_url')->first();
            if ($oldLogoUrlKey) {
                SystemSetting::where('key', 'system_logo_url')->delete();
            }
        }

        // Process other settings (excluding currency and timezone which are fixed)
        foreach ($validated as $key => $value) {
            // Skip currency/timezone (fixed values)
            if ($key === 'defaultCurrency' || $key === 'defaultTimezone') {
                continue;
            }
            // Chapa is always enabled; ignore updates to chapaEnabled
            if ($key === 'chapaEnabled') {
                continue;
            }

            $settingKey = match($key) {
                'systemName' => 'system_name',
                'stripeEnabled' => 'stripe_enabled',
                'telebirrEnabled' => 'telebirr_enabled',
                default => null,
            };

            if ($settingKey) {
                $oldValue = $oldSettings[$key] ?? null;
                if ($oldValue !== $value) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $value,
                    ];
                    SystemSetting::setValue($settingKey, $value);
                }
            }
        }

        // Log the settings update if there were any changes
        if (!empty($changes)) {
            AuditLogger::log('System Settings Updated', auth()->user(), null, [
                'changes' => $changes,
            ]);
        }

        return $this->getSystem();
    }

    public function getHotel(int $hotelId)
    {
        Hotel::findOrFail($hotelId); // Ensure hotel exists

        return response()->json([
            'timezone' => HotelSetting::getValue($hotelId, 'timezone'),
            'logoUrl' => HotelSetting::getValue($hotelId, 'logo_url'),
        ]);
    }

    public function updateHotel(UpdateHotelSettingsRequest $request, int $hotelId)
    {
        Hotel::findOrFail($hotelId); // Ensure hotel exists

        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            HotelSetting::setValue($hotelId, $key, $value);
        }

        return $this->getHotel($hotelId);
    }
}


