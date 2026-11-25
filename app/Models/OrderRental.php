<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRental extends Model
{
    protected $table = 'order_rentals';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'rental_start_date' => 'date',
        'rental_end_date' => 'date',
        'quantity' => 'integer',
        'rental_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}