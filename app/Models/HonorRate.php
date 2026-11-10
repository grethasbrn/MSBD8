<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HonorRate extends Model
{
    protected $primaryKey = 'role';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['amount' => 'float'];
}
