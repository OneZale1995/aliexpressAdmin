<?php

namespace App\Models;

use App\Traits\SerializeDateFormat;
use Illuminate\Database\Eloquent\Model;

class AliCategoryPropertyValue extends Model
{
    use SerializeDateFormat;

    protected $table = 'ali_category_property_values';

    protected $fillable = [
        'category_id',
        'property_id',
        'value_id',
        'is_sku_property',
        'shipping_template_id',
        'name',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'is_sku_property' => 'boolean',
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];
}
