<?php

namespace App\Models;

use App\Traits\SerializeDateFormat;
use Illuminate\Database\Eloquent\Model;

class AliCategory extends Model
{
    use SerializeDateFormat;

    protected $table = 'ali_categories';

    protected $fillable = [
        'category_id',
        'parent_id',
        'name',
        'is_leaf',
        'is_special',
        'is_visible',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'is_leaf' => 'boolean',
        'is_special' => 'boolean',
        'is_visible' => 'boolean',
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];
}
