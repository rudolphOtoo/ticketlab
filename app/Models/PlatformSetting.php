<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $table = 'platform_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    /** Cached for absent settings: the cache never stores null, which made every missing setting a query. */
    private const string MISSING = '__platform_setting_missing__';

    /**
     * Retrieve a setting value from cache or database with config/default fallback.
     *
     * The stored value is cached (memoised for the rest of the request); defaults are applied per caller.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::memo()->rememberForever(
            "platform_setting:{$key}",
            static fn(): mixed => static::query()->find($key)->value ?? self::MISSING,
        );

        return $value === self::MISSING ? config("app.{$key}", $default) : $value;
    }

    /**
     * Persist a platform setting to the database and clear the cache entry.
     */
    public static function set(string $key, mixed $value): void
    {
        $stringValue = match (true) {
            $value === true => '1',
            $value === false => '0',
            default => (string) $value,
        };

        static::query()->updateOrCreate(['key' => $key], ['value' => $stringValue]);

        Cache::memo()->forget("platform_setting:{$key}");
    }

    /**
     * Forget/delete a platform setting and clear the cache entry.
     */
    public static function forgetSetting(string $key): void
    {
        static::query()->where('key', $key)->delete();
        Cache::memo()->forget("platform_setting:{$key}");
    }

    /**
     * Determine if the application is currently running in Dedicated Bespoke Client mode.
     */
    public static function isDedicatedMode(): bool
    {
        $mode = static::get('deployment_mode', 'platform');
        $homeSlug = static::get('home_org_slug');

        return $mode === 'dedicated' && filled($homeSlug);
    }

    /**
     * Get the configured home/dedicated organization slug.
     */
    public static function getHomeOrgSlug(): ?string
    {
        $slug = static::get('home_org_slug');

        return filled($slug) ? (string) $slug : null;
    }
}
