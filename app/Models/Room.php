<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'type',
        'price',
        'status',
        'capacity',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => RoomStatus::class,
            'capacity' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the is_available attribute (computed from status)
     * This provides backward compatibility for code that still uses $room->is_available
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->status === RoomStatus::AVAILABLE;
    }

    /**
     * Get the hotel that owns this room
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Get reservations for this room
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Get images for this room
     */
    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('display_order');
    }
}

