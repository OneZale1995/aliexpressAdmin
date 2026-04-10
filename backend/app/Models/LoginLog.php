<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_login_logs';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'ip', 'user_agent', 'status', 'message', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
