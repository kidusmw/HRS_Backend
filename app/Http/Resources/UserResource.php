<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\Media;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array matching frontend UserListItem contract
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value === 'superadmin' ? 'super_admin' : $this->role->value,
            'hotelId' => $this->hotel_id,
            'hotelName' => $this->hotel?->name,
            'isActive' => $this->active,
            'lastActiveAt' => $this->created_at?->toIso8601String(),
            'phoneNumber' => $this->phone_number ?? null,
            'avatarUrl' => Media::url($this->avatar_path),
            'emailVerifiedAt' => $this->email_verified_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'supervisor' => $this->whenLoaded('supervisor', function () {
                return $this->supervisor ? [
                    'id' => $this->supervisor->id,
                    'name' => $this->supervisor->name,
                    'email' => $this->supervisor->email,
                ] : null;
            }, null),
        ];
    }
}

