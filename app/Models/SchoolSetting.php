<?php

namespace App\Models;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
    ];

    /**
     * Retrieve a setting value from cache/database.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = CacheService::rememberSchoolSettings(function () {
            return static::query()->pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

    /**
     * Retrieve setting as integer.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $val = static::get($key, $default);

        return is_numeric($val) ? (int) $val : $default;
    }

    /**
     * Retrieve setting as boolean.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key, $default);

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Store or update a setting and bust the cache.
     */
    public static function set(string $key, ?string $value, string $group = 'general', ?string $description = null): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => $value,
                'group' => $group,
                'description' => $description,
            ], fn ($v) => $v !== null)
        );

        CacheService::invalidateSchoolSettings();

        return $setting;
    }
}
