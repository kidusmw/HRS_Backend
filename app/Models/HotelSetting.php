<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hotel Settings Model
 * This model is used to store the settings for a hotel.
 * The settings are stored in the hotel_settings table.
 * The settings are stored in the key-value format.
 * The settings are stored in the value column as an array.
 * The settings are stored in the hotel_id column as the foreign key to the hotels table.
 * The settings are stored in the key column as the key of the setting.
 * The settings are stored in the value column as the value of the setting.
 * The settings are stored in the created_at and updated_at columns as the timestamps.
 */
class HotelSetting extends Model
{
    protected $fillable = [
        'hotel_id',
        'key',
        'value',
    ];

    // Keep value uncasted (can store scalar, json, or array as needed)

    /**
     * Get the hotel this setting belongs to
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Get a setting value by key for a hotel
     */
    public static function getValue(int $hotelId, string $key, mixed $default = null): mixed
    {
        $setting = static::where('hotel_id', $hotelId)
            ->where('key', $key)
            ->first();
        return $setting?->value ?? $default;
    }

    /**
     * Set a setting value by key for a hotel
     */
    public static function setValue(int $hotelId, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['hotel_id' => $hotelId, 'key' => $key],
            ['value' => $value]
        );
    }
}

