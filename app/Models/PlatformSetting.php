<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $table = 'platform_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Retrieve a setting value from cache or database with config/default fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("platform_setting:{$key}", function () use ($key, $default) {
            $setting = static::query()->find($key);

            if ($setting !== null && $setting->value !== null) {
                return $setting->value;
            }

            return config("app.{$key}", $default);
        });
    }

    /**
     * Persist a platform setting to the database and clear the cache entry.
     */
    public static function set(string $key, mixed $value): void
    {
        $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stringValue]
        );

        Cache::forget("platform_setting:{$key}");
    }

    /**
     * Forget/delete a platform setting and clear the cache entry.
     */
    public static function forgetSetting(string $key): void
    {
        static::query()->where('key', $key)->delete();
        Cache::forget("platform_setting:{$key}");
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
