<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_roles';
    protected $fillable = ['name', 'display_name', 'description', 'status', 'sort'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'admin_role_user');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'admin_permission_role');
    }
}
