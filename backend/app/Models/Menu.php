<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_menus';
    protected $fillable = [
        'parent_id', 'title', 'icon', 'path', 'component',
        'permission', 'type', 'hidden', 'sort', 'status',
    ];

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort');
    }

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }
}
