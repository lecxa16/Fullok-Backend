<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'user_id', 'ticket_id', 'redemption_id', 'tipo', 'puntos',
        'balance_despues', 'multiplicador_aplicado', 'earning_rate_snapshot',
        'point_value_snapshot', 'descripcion', 'expires_at', 'created_by', 'created_at',
    ];

    protected $casts = [
        'puntos' => 'integer',
        'balance_despues' => 'integer',
        'multiplicador_aplicado' => 'float',
        'earning_rate_snapshot' => 'float',
        'point_value_snapshot' => 'float',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemption::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
