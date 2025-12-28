<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * NOTE:
     * The `system_settings.value` column is a JSON column in the DB.
     * MySQL requires valid JSON text, so scalars must be JSON-encoded (e.g. "foo", true, 123).
     */

    private static function encodeJsonValue(mixed $value): mixed
    {
        if ($value === null) return null;
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function decodeJsonValue(mixed $value): mixed
    {
        if ($value === null) return null;
        $decoded = json_decode((string) $value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }

    /**
     * Get a setting value by key
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;
        $decoded = static::decodeJsonValue($setting->value);
        return $decoded ?? $default;
    }

    /**
     * Set a setting value by key
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => static::encodeJsonValue($value)]
        );
    }
}

