<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'check_in',
        'check_out',
        'status',
        'is_walk_in',
        'payment_method',
        'guests',
        'special_requests',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'is_walk_in' => 'boolean',
            'guests' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the attributes that should be included in the model's array/JSON representation.
     */
    protected function visible(): array
    {
        return [
            'id',
            'room_id',
            'user_id',
            'check_in',
            'check_out',
            'status',
            'is_walk_in',
            'payment_method',
            'guests',
            'special_requests',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get the room for this reservation
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the user who made this reservation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

