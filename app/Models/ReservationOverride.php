<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'manager_id',
        'new_status',
        'note',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}

