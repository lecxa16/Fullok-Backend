<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestion extends Model
{
    protected $fillable = ['survey_id', 'orden', 'pregunta', 'tipo', 'options', 'required'];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'orden' => 'integer',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
