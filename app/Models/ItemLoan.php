<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemLoan extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['borrowed_at' => 'datetime', 'returned_at' => 'datetime'];

    public function item(): BelongsTo     { return $this->belongsTo(InventoryItem::class, 'item_id'); }
    public function order(): BelongsTo    { return $this->belongsTo(Order::class); }
    public function borrower(): BelongsTo { return $this->belongsTo(User::class, 'borrowed_by'); }
}
