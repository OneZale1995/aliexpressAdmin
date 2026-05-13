<?php

namespace App\Models;

use App\Traits\SerializeDateFormat;
use Illuminate\Database\Eloquent\Model;

class AliCategoryProperty extends Model
{
    use SerializeDateFormat;

    protected $table = 'ali_category_properties';

    protected $fillable = [
        'category_id',
        'property_id',
        'is_sku_property',
        'name',
        'is_required',
        'is_key',
        'is_brand',
        'is_enum_prop',
        'is_multi_select',
        'is_input_prop',
        'has_unit',
        'has_customized_pic',
        'has_customized_name',
        'units',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'is_sku_property' => 'boolean',
        'is_required' => 'boolean',
        'is_key' => 'boolean',
        'is_brand' => 'boolean',
        'is_enum_prop' => 'boolean',
        'is_multi_select' => 'boolean',
        'is_input_prop' => 'boolean',
        'has_unit' => 'boolean',
        'has_customized_pic' => 'boolean',
        'has_customized_name' => 'boolean',
        'units' => 'array',
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];
}
