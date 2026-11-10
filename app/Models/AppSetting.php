<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public static function read(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }
}
