<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Survey extends Model
{
    protected $fillable = [
        'tipo', 'titulo', 'descripcion',
        'rating_question', 'rating_scale_max', 'points_reward',
        'response_window_hours', 'low_score_threshold',
        'schedule_interval_days', 'target_station_id',
        'estado', 'starts_at', 'ends_at', 'created_by',
    ];

    protected $casts = [
        'rating_scale_max' => 'integer',
        'points_reward' => 'integer',
        'response_window_hours' => 'integer',
        'low_score_threshold' => 'integer',
        'schedule_interval_days' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('orden');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(SurveyInvitation::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function targetStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'target_station_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLive(): bool
    {
        if ($this->estado !== 'active') return false;
        $now = Carbon::now();
        if ($this->starts_at && $this->starts_at->gt($now)) return false;
        if ($this->ends_at && $this->ends_at->lt($now)) return false;
        return true;
    }

    public function scopeLive($query)
    {
        $now = Carbon::now();
        return $query->where('estado', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
}
