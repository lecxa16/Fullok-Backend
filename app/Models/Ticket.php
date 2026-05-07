<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Ticket extends Model
{
    protected $fillable = [
        'user_id', 'station_id', 'folio', 'monto', 'litros', 'tipo_combustible',
        'fecha_ticket', 'foto_path', 'estado', 'motivo_rechazo',
        'revisado_por', 'revisado_at',
        'puntos_acreditados', 'multiplicador_aplicado',
        'earning_rate_snapshot', 'tier_snapshot',
    ];

    protected $casts = [
        'monto' => 'float',
        'litros' => 'float',
        'fecha_ticket' => 'date',
        'revisado_at' => 'datetime',
        'puntos_acreditados' => 'integer',
        'multiplicador_aplicado' => 'float',
        'earning_rate_snapshot' => 'float',
    ];

    protected $appends = ['foto_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function revisadoBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_path
            ? Storage::disk('public')->url($this->foto_path)
            : null;
    }
}
