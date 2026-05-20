<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SerializeDateFormat;

class OrderBlacklist extends Model
{
    use SerializeDateFormat;

    protected $table = 'order_blacklists';
    protected $fillable = [
        'team_id', 'name', 'phone', 'remark',
        'created_by', 'updated_by',
    ];

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
