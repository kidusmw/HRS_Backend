<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HotelSetting;
use App\Services\AuditLogger;
use App\Http\Requests\Admin\UpdateHotelSettingsRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /**
     * Get hotel settings for the admin's hotel
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns all hotel-specific settings.
     * Returns default values if settings are not configured.
     */
    public function get()
    {
        // get the user and hotel id from the authenticated user
        $user = auth()->user();
        $hotelId = $user->hotel_id;
        $hotel = \App\Models\Hotel::findOrFail($hotelId);

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $logoPath = $hotel?->logo_path;
        $resolvedLogoUrl = null;
        if ($logoPath) {
            //
            $resolvedLogoUrl = str_starts_with($logoPath, 'http')
                ? $logoPath
                : Storage::disk('public')->url($logoPath);
        }

        // Get all settings with defaults
        $settings = [
            'logoUrl' => $resolvedLogoUrl,
            'checkInTime' => HotelSetting::getValue($hotelId, 'check_in_time', '15:00'),
            'checkOutTime' => HotelSetting::getValue($hotelId, 'check_out_time', '11:00'),
            'cancellationHours' => (int) HotelSetting::getValue($hotelId, 'cancellation_hours', 24),
            'allowOnlineBooking' => (bool) HotelSetting::getValue($hotelId, 'allow_online_booking', true),
            'requireDeposit' => (bool) HotelSetting::getValue($hotelId, 'require_deposit', false),
            'depositPercentage' => (float) HotelSetting::getValue($hotelId, 'deposit_percentage', 0),
            'emailNotifications' => (bool) HotelSetting::getValue($hotelId, 'email_notifications', true),
            'smsNotifications' => (bool) HotelSetting::getValue($hotelId, 'sms_notifications', false),
        ];

        return response()->json(['data' => $settings]);
    }

    /**
     * Update hotel settings for the admin's hotel
     * @param UpdateHotelSettingsRequest $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Updates hotel-specific settings.
     * Logs changes via AuditLogger.
     */
    public function update(UpdateHotelSettingsRequest $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;
        $hotel = $user->hotel;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $changes = [];

        // Helper to parse booleans from form data ("1"/"0"/"true"/"false" strings)
        $parseBool = fn($key) => filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // Settings to save (key => value)
        $settingsToSave = [
            'check_in_time' => $request->input('checkInTime'),
            'check_out_time' => $request->input('checkOutTime'),
            'cancellation_hours' => $request->filled('cancellationHours') ? (int) $request->input('cancellationHours') : null,
            'allow_online_booking' => $parseBool('allowOnlineBooking'),
            'require_deposit' => $parseBool('requireDeposit'),
            'deposit_percentage' => $request->filled('depositPercentage') ? (float) $request->input('depositPercentage') : null,
            'email_notifications' => $parseBool('emailNotifications'),
            'sms_notifications' => $parseBool('smsNotifications'),
        ];

        // Save each setting (direct upsert to ensure persistence)
        foreach ($settingsToSave as $settingKey => $value) {
            if ($value === null) {
                continue;
            }

            $oldValue = HotelSetting::getValue($hotelId, $settingKey);

            DB::table('hotel_settings')->updateOrInsert(
                ['hotel_id' => $hotelId, 'key' => $settingKey],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );

            // Track changes for audit log (loose comparison for type differences)
            if ($oldValue != $value) {
                $camelKey = lcfirst(str_replace('_', '', ucwords($settingKey, '_')));
                $changes[$camelKey] = [
                    'old' => $oldValue,
                    'new' => $value,
                ];
            }
        }

        // Handle logo file upload or external URL
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'logo_' . $hotelId . '.' . $extension;
            $path = $file->storeAs("hotel-logos/{$hotelId}", $filename, 'public');

            $oldValue = $hotel?->logo_path;
            $changes['logoUrl'] = [
                'old' => $oldValue ? (str_starts_with($oldValue, 'http') ? $oldValue : Storage::disk('public')->url($oldValue)) : null,
                'new' => Storage::disk('public')->url($path),
            ];
            $hotel->logo_path = $path;
            $hotel->save();
        }

        // Log the settings update if there were any changes
        if (!empty($changes)) {
            AuditLogger::log('Hotel Settings Updated', $user, $hotelId, [
                'changes' => $changes,
            ]);
        }

        return $this->get();
    }
}


