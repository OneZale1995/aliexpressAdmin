<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SerializeDateFormat;

class Order extends Model
{
    use SerializeDateFormat;

    protected $table = 'orders';
    protected $fillable = [
        'ae_order_id', 'shop_id', 'status', 'payment_status',
        'delivery_status', 'order_display_status', 'antifraud_status',
        'total_amount', 'currency', 'platform_fee', 'affiliate_fee',
        'lianlian_fee', 'estimate_revenue', 'purchase_amount', 'express_fee',
        'buyer_name', 'buyer_phone', 'buyer_country_code',
        'receiver_name', 'receiver_phone', 'delivery_address',
        'receiver_country', 'receiver_region', 'receiver_city',
        'receiver_street', 'receiver_zip', 'logistics_type',
        'logistics_template',
        'tracking_number', 'logistic_order_id', 'sz56t_order_id', 'finish_reason', 'seller_comment',
        'admin_remark', 'backend_status', 'purchase_image', 'shipping_image',
        'purchase_date', 'shipping_date',
        'apply_qianze_at', 'ship_qianze_at',
        'fully_prepared', 'shipping_fee', 'logistics_fee',
        'eub_amazon_ratio', 'eub_base_fee', 'calculated_logistics_fee', 'logistics_fee_override',
        'ae_created_at', 'ae_paid_at', 'ae_updated_at',
        'cut_off_date', 'shipping_deadline',
        'marked_ship_at', 'actual_ship_at',
        'disputes', 'raw_data',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'affiliate_fee' => 'decimal:2',
        'lianlian_fee' => 'decimal:2',
        'estimate_revenue' => 'decimal:2',
        'purchase_amount' => 'decimal:2',
        'express_fee' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'logistics_fee' => 'decimal:2',
        'eub_amazon_ratio' => 'decimal:2',
        'eub_base_fee' => 'decimal:2',
        'calculated_logistics_fee' => 'decimal:2',
        'logistics_fee_override' => 'boolean',
        'logistic_order_id' => 'integer',
        'fully_prepared' => 'boolean',
        'disputes' => 'array',
        'raw_data' => 'array',
        'ae_created_at' => 'datetime',
        'ae_paid_at' => 'datetime',
        'ae_updated_at' => 'datetime',
        'cut_off_date' => 'datetime',
        'shipping_deadline' => 'datetime',
        'marked_ship_at' => 'datetime',
        'actual_ship_at' => 'datetime',
        'purchase_date' => 'date',
        'shipping_date' => 'date',
        'apply_qianze_at' => 'datetime',
        'ship_qianze_at' => 'datetime',
    ];

    protected $hidden = ['raw_data'];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
