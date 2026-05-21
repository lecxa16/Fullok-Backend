<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    protected $fillable = [
        'titulo', 'mensaje', 'icon', 'deeplink', 'prioridad',
        'audience_filter', 'total_users', 'push_sent_count', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'audience_filter' => 'array',
        'sent_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
