<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date:Y-m-d',
        'package_price' => 'float', 'addons_total' => 'float', 'total_amount' => 'float',
        'min_dp_amount' => 'float', 'compensation_amount' => 'float',
        'cancelled_at' => 'datetime', 'completed_at' => 'datetime', 'lunas_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function package(): BelongsTo  { return $this->belongsTo(Package::class); }
    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'order_addons')->withPivot('price_at_order');
    }
    public function payments(): HasMany    { return $this->hasMany(Payment::class); }
    public function assignments(): HasMany { return $this->hasMany(EventAssignment::class); }
    public function loans(): HasMany       { return $this->hasMany(ItemLoan::class); }
    public function statusLogs(): HasMany  { return $this->hasMany(OrderStatusLog::class)->orderBy('id'); }
}
