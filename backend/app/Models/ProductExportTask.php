<?php

namespace App\Models;

use App\Traits\SerializeDateFormat;
use Illuminate\Database\Eloquent\Model;

class ProductExportTask extends Model
{
    use SerializeDateFormat;

    protected $table = 'product_export_tasks';

    protected $fillable = [
        'operator_user_id',
        'trigger_type',
        'status',
        'format',
        'total_rows',
        'processed_rows',
        'progress',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'message',
        'options',
        'details',
        'started_at',
        'finished_at',
        'expires_at',
    ];

    protected $casts = [
        'options' => 'array',
        'details' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}