<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\UserRole;

class HotelResource extends JsonResource
{
    /**
     * Transform the resource into an array matching frontend HotelListItem contract
     */
    public function toArray(Request $request): array
    {
        $admin = $this->users()->where('role', UserRole::ADMIN)->first();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address ?? '',
            'timezone' => $this->timezone,
            'adminName' => $admin?->name,
            'roomsCount' => $this->rooms()->count(),
            'phoneNumber' => $this->phone,
            'email' => $this->email,
        ];
    }
}

