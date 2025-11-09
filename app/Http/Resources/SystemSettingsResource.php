<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array matching frontend SystemSettingsDto contract
     */
    public function toArray(Request $request): array
    {
        return [
            'systemName' => $this->resource['systemName'] ?? config('app.name'),
            'systemLogoUrl' => $this->resource['systemLogoUrl'] ?? null,
            'defaultCurrency' => $this->resource['defaultCurrency'] ?? 'USD',
            'defaultTimezone' => $this->resource['defaultTimezone'] ?? 'UTC',
        ];
    }
}

