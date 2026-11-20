<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A value the superadmin can change from the panel without a deploy. All of them are read in one cached query;
 * every save clears that cache.
 */
class SiteSetting extends Model
{
    private const CACHE_KEY = 'site-settings';

    protected $fillable = ['key', 'value', 'updated_by'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Every stored value, by key. A missing table (a fresh install before migrating) reads as no values.
     *
     * @return array<string, string|null>
     */
    public static function allValues(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            self::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
