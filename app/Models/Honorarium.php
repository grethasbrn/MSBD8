<?php

namespace App\Models;

use App\Models\User;
use App\Models\EventAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Honorarium extends Model
{
    protected $table = 'honorariums';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['amount' => 'float', 'paid_at' => 'datetime'];

    public function assignment(): BelongsTo 
    { 
        return $this->belongsTo(EventAssignment::class, 'assignment_id'); 
    }
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
