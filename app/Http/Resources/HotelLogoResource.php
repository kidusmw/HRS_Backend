<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HotelLogoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = null;
        if ($this->logo_path) {
            $generated = Storage::disk('public')->url($this->logo_path);
            $url = str_starts_with($generated, 'http')
                ? $generated
                : rtrim(config('app.url'), '/') . $generated;
        }

        return [
            'hotelId' => $this->id,
            'logoUrl' => $url,
        ];
    }
}


