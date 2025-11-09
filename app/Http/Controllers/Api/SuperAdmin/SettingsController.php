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

class SettingsController extends Controller
{
    public function getSystem()
    {
        $settings = [
            'systemName' => SystemSetting::getValue('system_name', config('app.name')),
            'systemLogoUrl' => SystemSetting::getValue('system_logo_url'),
            'defaultCurrency' => SystemSetting::getValue('default_currency', 'USD'),
            'defaultTimezone' => SystemSetting::getValue('default_timezone', 'UTC'),
        ];

        return new SystemSettingsResource($settings);
    }

    public function updateSystem(UpdateSystemSettingsRequest $request)
    {
        // Get current settings before update
        $oldSettings = [
            'systemName' => SystemSetting::getValue('system_name', config('app.name')),
            'systemLogoUrl' => SystemSetting::getValue('system_logo_url'),
            'defaultCurrency' => SystemSetting::getValue('default_currency', 'USD'),
            'defaultTimezone' => SystemSetting::getValue('default_timezone', 'UTC'),
        ];

        $validated = $request->validated();
        $changes = [];

        foreach ($validated as $key => $value) {
            $settingKey = match($key) {
                'systemName' => 'system_name',
                'systemLogoUrl' => 'system_logo_url',
                'defaultCurrency' => 'default_currency',
                'defaultTimezone' => 'default_timezone',
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


