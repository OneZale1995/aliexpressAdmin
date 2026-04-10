<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_files';
    protected $fillable = [
        'user_id', 'original_name', 'filename', 'path', 'mime_type', 'size', 'disk',
    ];

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }
}
