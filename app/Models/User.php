<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    public $timestamps = false;   // created_at/updated_at diurus oleh database

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'specialization', 'is_active'];
    protected $hidden   = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }
}
