<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionUserProgress extends Model
{
    protected $table = 'promotion_user_progress';

    protected $fillable = [
        'promotion_id', 'user_id', 'progress_current', 'completed_at', 'points_awarded',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'progress_current' => 'integer',
        'points_awarded' => 'integer',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
