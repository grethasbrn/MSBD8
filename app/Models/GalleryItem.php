<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['is_published' => 'boolean'];
}
