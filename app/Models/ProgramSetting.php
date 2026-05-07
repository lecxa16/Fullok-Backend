<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'description', 'sort_order', 'updated_by'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getTypedValueAttribute(): mixed
    {
        return self::cast($this->value, $this->type);
    }

    public static function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : null,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'json' => json_decode((string) $value, true),
            default => (string) $value,
        };
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $row = self::where('key', $key)->first();
        return $row ? $row->typed_value : $default;
    }
}
