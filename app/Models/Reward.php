<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Reward extends Model
{
    protected $fillable = [
        'nombre', 'descripcion', 'costo_puntos', 'tipo', 'imagen_url',
        'stock', 'inicia_at', 'termina_at', 'activo',
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(RewardInventory::class);
    }

    /**
     * Stock total disponible = SUM de inventarios por sucursal.
     * Si no hay inventarios, asume el stock global del reward (compat).
     */
    public function totalStock(): int
    {
        return (int) $this->inventories()->sum('stock');
    }

    protected $casts = [
        'costo_puntos' => 'integer',
        'stock' => 'integer',
        'activo' => 'boolean',
        'inicia_at' => 'datetime',
        'termina_at' => 'datetime',
    ];

    public function scopeAvailable($query)
    {
        $now = Carbon::now();
        return $query
            ->where('activo', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('inicia_at')->orWhere('inicia_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('termina_at')->orWhere('termina_at', '>=', $now);
            });
    }

    public function isAvailable(): bool
    {
        if (! $this->activo) return false;
        $now = Carbon::now();
        if ($this->inicia_at && $this->inicia_at->gt($now)) return false;
        if ($this->termina_at && $this->termina_at->lt($now)) return false;
        if ($this->stock !== null && $this->stock <= 0) return false;
        return true;
    }
}
