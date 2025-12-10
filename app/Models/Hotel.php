<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'country',
        'phone',
        'email',
        'description',
        'timezone',
        'logo_path',
        'primary_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get users assigned to this hotel
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get rooms in this hotel
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get audit logs for this hotel
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get hotel settings
     */
    public function settings(): HasMany
    {
        return $this->hasMany(HotelSetting::class);
    }

    /**
     * Get images for this hotel.
     */
    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class)->orderBy('display_order');
    }

    /**
     * Get the primary admin user for this hotel
     */
    public function primaryAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_admin_id');
    }
}

