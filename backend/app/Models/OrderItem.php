<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SerializeDateFormat;

class OrderItem extends Model
{
    use SerializeDateFormat;

    protected $table = 'order_items';
    protected $fillable = [
        'order_id', 'ae_order_line_id', 'ae_item_id', 'ae_sku_id',
        'sku_code', 'name', 'img_url', 'item_price', 'currency',
        'quantity', 'total_amount', 'properties', 'sku_attributes',
        'line_fee', 'item_affiliate_fee', 'item_estimate_revenue',
        'issue_status', 'logistic_name', 'logistic_storage_type',
    ];

    protected $casts = [
        'item_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'line_fee' => 'decimal:2',
        'item_affiliate_fee' => 'decimal:2',
        'item_estimate_revenue' => 'decimal:2',
        'properties' => 'array',
        'sku_attributes' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
