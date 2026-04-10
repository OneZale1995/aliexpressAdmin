<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationLog extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_operation_logs';
    protected $fillable = [
        'user_id', 'user_name', 'method', 'path', 'ip',
        'input', 'status_code', 'response', 'duration',
    ];
}
