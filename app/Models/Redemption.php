<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redemption extends Model
{
    protected $fillable = [
        'user_id', 'reward_id', 'station_id', 'puntos_gastados', 'codigo_unico',
        'estado', 'reward_nombre_snapshot', 'station_nombre_snapshot', 'usado_at',
    ];

    protected $casts = [
        'puntos_gastados' => 'integer',
        'usado_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
