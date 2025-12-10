<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RoomImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = null;
        if ($this->image_url) {
            $generated = Storage::disk('public')->url($this->image_url);
            // if the generated url starts with http, then use it, otherwise add the app.url to the generated url
            $url = str_starts_with($generated, 'http')
                ? $generated
                : rtrim(config('app.url'), '/') . $generated;
        }

        return [
            'id' => $this->id,
            'roomId' => $this->room_id,
            'imageUrl' => $url,
            'altText' => $this->alt_text,
            'displayOrder' => $this->display_order,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}


