<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'type',
        'severity',
        'message',
        'status',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}

