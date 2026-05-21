<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    protected $fillable = [
        'tipo', 'titulo', 'descripcion', 'imagen_url', 'color_hex', 'emoji',
        'starts_at', 'ends_at', 'estado', 'prioridad',
        'multiplier_value', 'fuel_type_filter', 'min_tier',
        'goal_type', 'goal_target', 'bonus_points',
        'max_redemptions_per_user', 'total_budget_points', 'points_issued',
        'deeplink_url', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'multiplier_value' => 'decimal:2',
        'prioridad' => 'integer',
        'goal_target' => 'integer',
        'bonus_points' => 'integer',
        'max_redemptions_per_user' => 'integer',
        'total_budget_points' => 'integer',
        'points_issued' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(PromotionUserProgress::class);
    }

    /**
     * Está dentro de su ventana de fechas. No considera tier ni filtros de
     * usuario — eso se evalúa en otros lados (ej. al calcular multiplier).
     */
    public function isLive(): bool
    {
        $now = Carbon::now();
        if ($this->estado !== 'active') return false;
        if ($this->starts_at && $this->starts_at->gt($now)) return false;
        if ($this->ends_at && $this->ends_at->lt($now)) return false;
        if ($this->total_budget_points !== null
            && $this->points_issued >= $this->total_budget_points) return false;
        return true;
    }

    /**
     * Aplica a este user/ticket? (para multiplier al aprobar tickets).
     *
     * @param string $tierUser  bronze/silver/gold
     * @param string $fuel      magna/premium/diesel
     */
    public function appliesTo(string $tierUser, string $fuel): bool
    {
        if (! $this->isLive()) return false;
        if ($this->fuel_type_filter && $this->fuel_type_filter !== $fuel) return false;
        if ($this->min_tier) {
            $rank = ['bronze' => 1, 'silver' => 2, 'gold' => 3];
            if (($rank[$tierUser] ?? 0) < ($rank[$this->min_tier] ?? 0)) return false;
        }
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
