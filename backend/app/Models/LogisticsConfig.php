<?php

namespace App\Models;

use App\Traits\SerializeDateFormat;
use Illuminate\Database\Eloquent\Model;

class LogisticsConfig extends Model
{
    use SerializeDateFormat;

    protected $table = 'admin_logistics_configs';

    protected $fillable = [
        'scope_type',
        'scope_id',
        'provider',
        'enabled',
        'config',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'array',
    ];
}
