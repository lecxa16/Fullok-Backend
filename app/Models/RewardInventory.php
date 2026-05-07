<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardInventory extends Model
{
    protected $fillable = ['reward_id', 'station_id', 'stock'];

    protected $casts = ['stock' => 'integer'];

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
