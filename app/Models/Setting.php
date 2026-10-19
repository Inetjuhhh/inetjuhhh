<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A setting of the whole site (the settings table). Read on every page, so all of them are kept
 * in the cache, and that copy is thrown away as soon as one changes.
 */
class Setting extends Model
{
    private const CACHE_KEY = 'site-settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'value' => 'json',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $value === null
            ? static::query()->whereKey($key)->delete()
            : static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }
}
