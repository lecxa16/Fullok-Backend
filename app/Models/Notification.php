<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'tipo', 'titulo', 'mensaje', 'icon',
        'deeplink', 'payload', 'prioridad',
        'leida_at', 'push_sent_at', 'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'leida_at' => 'datetime',
        'push_sent_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
