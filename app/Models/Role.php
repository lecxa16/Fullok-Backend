<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function getBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }
}
