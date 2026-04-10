<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SerializeDateFormat;

class Team extends Model
{
    use SerializeDateFormat;

    protected $table = 'teams';
    protected $fillable = ['name', 'admin_user_id', 'status', 'description'];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members');
    }

    public function shops()
    {
        return $this->hasMany(Shop::class);
    }
}
