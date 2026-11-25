<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function orderRentals(): HasMany { return $this->hasMany(OrderRental::class); }
}


