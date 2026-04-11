<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SerializeDateFormat;

class OrderSyncTask extends Model
{
    use SerializeDateFormat;

    protected $table = 'order_sync_tasks';

    protected $fillable = [
        'operator_user_id', 'trigger_type', 'status',
        'total_shops', 'processed_shops', 'failed_shops',
        'synced_orders', 'progress', 'current_shop_name',
        'message', 'options', 'details',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'options' => 'array',
        'details' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
