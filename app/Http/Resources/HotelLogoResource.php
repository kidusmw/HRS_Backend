<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\Media;

class HotelLogoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = Media::url($this->logo_path);

        return [
            'hotelId' => $this->id,
            'logoUrl' => $url,
        ];
    }
}


