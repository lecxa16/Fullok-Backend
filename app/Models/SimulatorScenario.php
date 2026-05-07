<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulatorScenario extends Model
{
    protected $fillable = ['nombre', 'descripcion', 'supuestos', 'created_by'];

    protected $casts = ['supuestos' => 'array'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
