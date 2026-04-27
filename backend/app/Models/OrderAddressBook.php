<?php

namespace App\Models;

use App\Traits\SerializeDateFormat;
use Illuminate\Database\Eloquent\Model;

class OrderAddressBook extends Model
{
    use SerializeDateFormat;

    protected $table = 'admin_order_address_books';

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'company',
        'post_code',
        'phone',
        'mobile',
        'email',
        'id_type',
        'id_no',
        'nation',
        'province',
        'city',
        'county',
        'address',
        'gis',
        'linker',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}