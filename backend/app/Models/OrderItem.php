<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SerializeDateFormat;

class OrderItem extends Model
{
    use SerializeDateFormat;

    public const SKU_SYNC_STATUS_PENDING = 'pending';
    public const SKU_SYNC_STATUS_SYNCED = 'synced';
    public const SKU_SYNC_STATUS_PRODUCT_NOT_FOUND = 'product_not_found';
    public const SKU_SYNC_STATUS_FAILED = 'failed';

    protected $table = 'order_items';
    protected $fillable = [
        'order_id', 'ae_order_line_id', 'ae_item_id', 'ae_sku_id',
        'sku_code', 'name', 'img_url', 'item_price', 'currency',
        'quantity', 'total_amount', 'properties', 'sku_attributes',
        'sku_sync_status',
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

    public function scopeNeedsSkuSync($query)
    {
        return $query->where(function ($innerQuery) {
            $innerQuery->whereNull('sku_sync_status')
                ->orWhereIn('sku_sync_status', [
                    self::SKU_SYNC_STATUS_PENDING,
                    self::SKU_SYNC_STATUS_FAILED,
                ]);
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ae_item_id', 'ae_item_id')
            ->where('products.shop_id', $this->order?->shop_id ?? 0);
    }
}
