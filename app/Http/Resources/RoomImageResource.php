<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\Media;

class RoomImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = Media::url($this->image_url);

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


