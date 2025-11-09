<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array matching frontend AuditLogItem contract
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp->toIso8601String(),
            'userName' => $this->user?->name ?? 'System',
            'userId' => $this->user_id,
            'action' => $this->action,
            'hotelId' => $this->hotel_id,
            'hotelName' => $this->hotel?->name,
            'meta' => $this->meta,
        ];
    }
}

