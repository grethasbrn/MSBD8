<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'price' => 'float', 'is_active' => 'boolean',
        'extra_dancers' => 'integer', 'extra_musicians' => 'integer',
    ];
}
