<?php

namespace App\Models;

use App\Traits\SerializeDateFormat;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use SerializeDateFormat;

    protected $table = 'products';

    protected $fillable = [
        'shop_id',
        'ae_item_id',
        'category_id',
        'category_name',
        'owner_member_id',
        'owner_member_seq',
        'bulk_discount',
        'bulk_order',
        'base_unit',
        'add_unit',
        'add_weight',
        'currency_code',
        'delivery_time',
        'freight_template_id',
        'package_height',
        'package_length',
        'package_width',
        'lot_num',
        'title_en',
        'title_ru',
        'main_image_url',
        'price',
        'status_type',
        'unit',
        'promise_template_id',
        'product_reduce_strategy',
        'gross_weight',
        'sizechart_id',
        'package_type',
        'descriptions',
        'media',
        'subjects',
        'marketing_images',
        'keywords',
        'gtin',
        'classifier_codes',
        'detail',
        'mobile_detail',
        'ticket_allocate_type',
        'video',
        'properties',
        'skus',
        'raw_data',
        'ae_created_at',
        'ae_updated_at',
        'synced_at',
    ];

    protected $casts = [
        'bulk_discount' => 'integer',
        'bulk_order' => 'integer',
        'add_weight' => 'decimal:3',
        'gross_weight' => 'decimal:3',
        'package_type' => 'boolean',
        'descriptions' => 'array',
        'media' => 'array',
        'subjects' => 'array',
        'marketing_images' => 'array',
        'keywords' => 'array',
        'classifier_codes' => 'array',
        'video' => 'array',
        'properties' => 'array',
        'skus' => 'array',
        'raw_data' => 'array',
        'ae_created_at' => 'datetime',
        'ae_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'ae_item_id', 'ae_item_id')
            ->where('order_items.shop_id', $this->shop_id ?? 0);
    }
}
