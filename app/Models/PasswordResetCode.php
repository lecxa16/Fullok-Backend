<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    // No usa created_at/updated_at automáticos
    // porque solo tiene created_at manual
    public $timestamps = false;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}