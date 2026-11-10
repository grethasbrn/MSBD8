<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $appends = ['proof_url'];
    protected $casts = ['amount' => 'float', 'verified_at' => 'datetime', 'created_at' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    public function getProofUrlAttribute(): ?string
    {
        return $this->proof_path ? Storage::disk('public')->url($this->proof_path) : null;
    }
}
