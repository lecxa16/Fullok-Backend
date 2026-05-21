<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SurveyInvitation extends Model
{
    protected $fillable = [
        'survey_id', 'user_id', 'ticket_id', 'redemption_id', 'station_id',
        'sent_at', 'expires_at', 'responded_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemption::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function response(): HasOne
    {
        return $this->hasOne(SurveyResponse::class, 'invitation_id');
    }

    public function isOpen(): bool
    {
        if ($this->responded_at) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }
}
