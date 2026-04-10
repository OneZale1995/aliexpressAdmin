<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DictType extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_dict_types';
    protected $fillable = ['name', 'code', 'status', 'description'];

    public function items()
    {
        return $this->hasMany(DictData::class);
    }

    public static function getItems(string $code)
    {
        $type = static::where('code', $code)->where('status', 1)->first();
        if (!$type) return [];
        return $type->items()->where('status', 1)->orderBy('sort')->get();
    }
}
