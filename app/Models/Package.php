<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'price' => 'float', 'is_active' => 'boolean',
        'dancers_needed' => 'integer', 'musicians_needed' => 'integer', 'helpers_needed' => 'integer',
    ];
}
