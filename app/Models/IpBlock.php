<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * An address the superadmin shut out of the whole site. The list is cached, so checking it
 * on every request costs one cache read; every change clears the cache.
 */
class IpBlock extends Model
{
    private const CACHE_KEY = 'ip-blocks';

    protected $fillable = ['ip', 'note', 'blocked_by', 'hits', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public static function isBlocked(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        try {
            return in_array($ip, Cache::rememberForever(self::CACHE_KEY, fn () => self::pluck('ip')->all()), true);
        } catch (Throwable) {
            // No table yet (first deploy) or the database is down: never lock everyone out.
            return false;
        }
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
