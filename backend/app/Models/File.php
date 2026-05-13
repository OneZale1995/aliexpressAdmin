<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use \App\Traits\SerializeDateFormat;

    protected $table = 'admin_files';
    protected $fillable = [
        'user_id', 'original_name', 'filename', 'path', 'mime_type', 'size', 'disk',
    ];

    public function getUrlAttribute()
    {
        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }
}
