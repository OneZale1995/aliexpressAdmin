<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_system_configs';
    protected $fillable = [
        'group', 'key', 'value', 'name', 'type', 'options', 'description', 'sort',
    ];

    public static function getByKey(string $key, $default = null)
    {
        $config = static::where('key', $key)->first();
        return $config ? $config->value : $default;
    }

    public static function getByGroup(string $group)
    {
        return static::where('group', $group)->orderBy('sort')->get()
            ->pluck('value', 'key')
            ->toArray();
    }
}
