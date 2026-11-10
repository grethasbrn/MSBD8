<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventAssignment extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'event_date' => 'date:Y-m-d',
        'responded_at' => 'datetime', 'attended_at' => 'datetime', 'created_at' => 'datetime',
    ];

    public function order(): BelongsTo     { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function honorarium(): HasOne   { return $this->hasOne(Honorarium::class, 'assignment_id'); }
}
