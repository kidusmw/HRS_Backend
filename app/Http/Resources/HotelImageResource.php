<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HotelImageResource extends JsonResource
{
    /**
     * Transform the resource into an array matching frontend hotel image contract.
     */
    public function toArray(Request $request): array
    {
        $url = null;
        if ($this->image_url) {
            $generated = Storage::disk('public')->url($this->image_url);
            // Ensure absolute URL even when Storage::url returns a relative path
            if (str_starts_with($generated, 'http')) {
                $url = $generated;
            } else {
                $appUrl = rtrim(config('app.url'), '/');
                $url = $appUrl . $generated;
            }
        }

        return [
            'id' => $this->id,
            'hotelId' => $this->hotel_id,
            'imageUrl' => $url,
            'altText' => $this->alt_text,
            'displayOrder' => $this->display_order,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}


