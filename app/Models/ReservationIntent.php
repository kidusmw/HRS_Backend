<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hotel_id',
        'room_type',
        'check_in',
        'check_out',
        'nights',
        'total_amount',
        'currency',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'nights' => 'integer',
            'total_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
