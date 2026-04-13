<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLogistics extends Model
{
    protected $table = 'order_logistics';

    protected $fillable = [
        'order_id',
        'is_primary',
        'logistics_mode',
        'provider_code',
        'provider_name',
        'template_code',
        'external_order_id',
        'platform_logistic_order_id',
        'handover_list_id',
        'handover_list_status',
        'tracking_number',
        'tracking_url',
        'logistic_status',
        'payload',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'platform_logistic_order_id' => 'integer',
        'handover_list_id' => 'integer',
        'payload' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}