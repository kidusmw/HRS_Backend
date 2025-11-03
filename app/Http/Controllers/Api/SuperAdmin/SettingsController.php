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

class SettingsController extends Controller
{
    public function getSystem()
    {
        $settings = [
            'systemName' => SystemSetting::getValue('system_name', config('app.name')),
            'systemLogoUrl' => SystemSetting::getValue('system_logo_url'),
            'defaultCurrency' => SystemSetting::getValue('default_currency', 'USD'),
            'defaultTimezone' => SystemSetting::getValue('default_timezone', config('app.timezone')),
        ];

        return new SystemSettingsResource($settings);
    }

    public function updateSystem(UpdateSystemSettingsRequest $request)
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            $settingKey = match($key) {
                'systemName' => 'system_name',
                'systemLogoUrl' => 'system_logo_url',
                'defaultCurrency' => 'default_currency',
                'defaultTimezone' => 'default_timezone',
                default => null,
            };

            if ($settingKey) {
                SystemSetting::setValue($settingKey, $value);
            }
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


