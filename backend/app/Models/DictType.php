<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
        return Cache::remember("dict:code:{$code}", 3600, function () use ($code) {
            $type = static::where('code', $code)->where('status', 1)->first();
            if (!$type) return [];
            return $type->items()->where('status', 1)->orderBy('sort')->get()->toArray();
        });
    }

    public static function getBatchItems(array $codes): array
    {
        $result = [];
        $missedCodes = [];

        foreach ($codes as $code) {
            $cached = Cache::get("dict:code:{$code}");
            if ($cached !== null) {
                $result[$code] = $cached;
            } else {
                $missedCodes[] = $code;
            }
        }

        if (!empty($missedCodes)) {
            $types = static::with(['items' => fn($q) => $q->where('status', 1)->orderBy('sort')])
                ->where('status', 1)
                ->whereIn('code', $missedCodes)
                ->get();

            foreach ($types as $type) {
                $items = $type->items->toArray();
                Cache::put("dict:code:{$type->code}", $items, 3600);
                $result[$type->code] = $items;
            }

            foreach ($missedCodes as $code) {
                if (!isset($result[$code])) {
                    Cache::put("dict:code:{$code}", [], 3600);
                    $result[$code] = [];
                }
            }
        }

        return $result;
    }

    public static function clearCache(?string $code = null): void
    {
        if ($code) {
            Cache::forget("dict:code:{$code}");
        }
    }
}
