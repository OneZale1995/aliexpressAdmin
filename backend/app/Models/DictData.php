<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DictData extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_dict_data';
    protected $fillable = ['dict_type_id', 'label', 'value', 'status', 'sort', 'description'];

    public function dictType()
    {
        return $this->belongsTo(DictType::class);
    }
}
