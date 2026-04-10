<?php

namespace App\Traits;

use DateTimeInterface;

trait SerializeDateFormat
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
