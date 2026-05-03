<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_system_configs';
    protected $fillable = [
        'group', 'key', 'value', 'name', 'type', 'options', 'description', 'sort',
    ];

    public static function getByKey(string $key, $default = null)
    {
        return Cache::remember("sys_config:key:{$key}", 3600, function () use ($key, $default) {
            $config = static::where('key', $key)->first();
            return $config ? $config->value : $default;
        });
    }

    public static function getByGroup(string $group)
    {
        return Cache::remember("sys_config:group:{$group}", 3600, function () use ($group) {
            return static::where('group', $group)->orderBy('sort')->get()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public static function clearCache(?string $group = null, ?string $key = null): void
    {
        if ($key) {
            Cache::forget("sys_config:key:{$key}");
        }
        if ($group) {
            Cache::forget("sys_config:group:{$group}");
        }
    }
}
