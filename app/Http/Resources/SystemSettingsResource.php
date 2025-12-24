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
            'defaultCurrency' => 'USD', // Always USD
            'defaultTimezone' => 'UTC', // Always UTC
            // Payment options
            'chapaEnabled' => $this->resource['chapaEnabled'] ?? false,
            'stripeEnabled' => $this->resource['stripeEnabled'] ?? false,
            'telebirrEnabled' => $this->resource['telebirrEnabled'] ?? false,
        ];
    }
}

