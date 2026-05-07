<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramSettingHistory extends Model
{
    protected $table = 'program_settings_history';
    public $timestamps = false;

    protected $fillable = ['key', 'old_value', 'new_value', 'changed_by', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
