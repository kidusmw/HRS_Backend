<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

class SystemSettingsController extends Controller
{
    public function __invoke()
    {
        $logoPath = SystemSetting::getValue('system_logo_path');

        $systemLogoUrl = null;
        if ($logoPath) {
            // Ignore invalid legacy values (data URLs or external URLs)
            if (
                !str_starts_with($logoPath, 'data:image/') &&
                !str_starts_with($logoPath, 'http://') &&
                !str_starts_with($logoPath, 'https://')
            ) {
                $systemLogoUrl = Storage::disk('public')->url($logoPath);
            }
        }

        return response()->json([
            'data' => [
                'systemName' => SystemSetting::getValue('system_name', config('app.name')),
                'systemLogoUrl' => $systemLogoUrl,
            ],
        ]);
    }
}


