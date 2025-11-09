<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupResource extends JsonResource
{
    /**
     * Transform the resource into an array matching frontend BackupItem contract
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'hotelId' => $this->hotel_id,
            'hotelName' => $this->hotel?->name,
            'status' => $this->status,
            'sizeBytes' => $this->size_bytes,
            'path' => $this->path,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}

