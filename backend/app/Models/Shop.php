<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SerializeDateFormat;

class Shop extends Model
{
    use SerializeDateFormat;

    protected $table = 'shops';
    protected $fillable = [
        'name', 'email', 'status', 'access_token', 'token_invalid_at',
        'default_shipping_fee', 'logistics_template_id',
        'logistics_template_name', 'logistics_route',
        'order_updated_at', 'user_id', 'team_id',
        'created_by', 'updated_by',
    ];

    protected $hidden = ['access_token'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
