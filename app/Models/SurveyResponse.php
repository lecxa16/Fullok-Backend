<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $fillable = [
        'invitation_id', 'survey_id', 'user_id', 'station_id',
        'score', 'feedback', 'answers', 'points_awarded',
        'low_score_alerted_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'score' => 'integer',
        'points_awarded' => 'integer',
        'low_score_alerted_at' => 'datetime',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(SurveyInvitation::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
