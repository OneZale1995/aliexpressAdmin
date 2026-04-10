<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_permissions';
    protected $fillable = ['name', 'display_name', 'guard_name', 'parent_id', 'description', 'sort'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'admin_permission_role');
    }

    public function children()
    {
        return $this->hasMany(Permission::class, 'parent_id')->orderBy('sort');
    }

    public function parent()
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }
}
